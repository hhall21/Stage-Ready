<?php

namespace Tests\Feature;

use App\Models\CoachingHandover;
use App\Models\User;
use App\Services\CoachBilling;
use App\Services\StripeGateway;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class RequestRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        Notification::fake();
        $this->freezeTime();
    }

    public function test_reset_email_limit_normalises_email_and_cannot_be_bypassed_by_rotating_ips(): void
    {
        $user = User::factory()->create(['email' => 'coach@example.test']);
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.$attempt])
                ->postJson('/api/forgot-password', ['email' => $attempt % 2 ? 'COACH@example.test' : ' coach@example.test '])->assertOk();
        }
        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.6'])
            ->postJson('/api/forgot-password', ['email' => $user->email])->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertGreaterThanOrEqual(3599, (int) $response->headers->get('Retry-After'));
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        $this->travel(61)->seconds();
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertTooManyRequests();
        $this->travel(60)->minutes();
        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        Notification::assertSentToTimes($user, ResetPassword::class, 2);
    }

    public function test_email_request_exhaustion_does_not_block_a_valid_password_reset_and_reset_attempts_are_limited(): void
    {
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/forgot-password', ['email' => "missing{$attempt}@example.test"])->assertOk();
        }
        $this->postJson('/api/forgot-password', ['email' => 'another@example.test'])->assertTooManyRequests();
        $payload = ['email' => $user->email, 'token' => Password::createToken($user),
            'password' => 'ReplacementPassword123', 'password_confirmation' => 'ReplacementPassword123'];
        $this->postJson('/api/reset-password', $payload)->assertOk();
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/api/reset-password', $payload)->assertUnprocessable();
        }
        $this->postJson('/api/reset-password', $payload)->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertTrue(Hash::check('ReplacementPassword123', $user->fresh()->password));
    }

    public function test_outer_limit_covers_all_paths_and_untrusted_forwarded_ips_without_starving_stripe_deliveries(): void
    {
        $executions = 0;
        Route::get('/_request-limit-test', function () use (&$executions) {
            $executions++;

            return response()->noContent();
        });
        for ($attempt = 0; $attempt < 600; $attempt++) {
            $this->get('/_request-limit-test')->assertNoContent();
        }
        $this->getJson('/api/user')->assertTooManyRequests()->assertHeader('Retry-After', '60');
        $this->get('/a-different-path', ['X-Forwarded-For' => '198.51.100.20'])->assertTooManyRequests();
        $this->assertSame(600, $executions);

        config(['services.stripe.webhook_secret' => 'whsec_rate_limits', 'services.stripe.live_mode' => false]);
        $this->postJson('/stripe/webhook', [])->assertStatus(400);
        $payload = json_encode(['id' => 'evt_rate_limits', 'object' => 'event', 'created' => now()->timestamp,
            'type' => 'test.rate_limits', 'livemode' => false, 'data' => ['object' => []]], JSON_THROW_ON_ERROR);
        $signature = 't='.now()->timestamp.',v1='.hash_hmac('sha256', now()->timestamp.'.'.$payload, 'whsec_rate_limits');
        $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload)
            ->assertOk()->assertJsonPath('received', true);
        $this->assertDatabaseHas('stripe_webhook_events', ['id' => 'evt_rate_limits']);
        $this->assertDatabaseCount('coach_payments', 0);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.21'])->get('/_request-limit-test')->assertNoContent();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
        $this->travel(61)->seconds();
        $this->get('/_request-limit-test')->assertNoContent();
    }

    public function test_application_budget_is_shared_across_routes_and_ips_but_separate_for_each_account(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->actingAs($first);
        for ($attempt = 0; $attempt < 180; $attempt++) {
            $this->getJson('/api/user')->assertOk();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.22'])->getJson('/api/coaching-handovers')->assertTooManyRequests();
        $this->actingAs($second)->getJson('/api/user')->assertOk();
        $this->travel(61)->seconds();
        $this->actingAs($first)->getJson('/api/user')->assertOk();
    }

    public function test_write_limit_blocks_mutations_before_records_change_while_reads_remain_available(): void
    {
        $client = User::factory()->coached()->create();
        $check = $client->dailyCheckIns()->create(['check_in_date' => today()]);
        $base = '/api/clients/'.$client->id;
        $this->actingAs($client);
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->patchJson($base.'/daily-check-ins/'.$check->id, ['notes' => (string) $attempt])->assertOk();
        }
        $this->putJson($base.'/end-of-day-nutrition', ['hunger_level' => 4])->assertTooManyRequests();
        $this->assertNull($check->fresh()->hunger_level);
        $this->getJson($base.'/daily-check-ins/'.$check->id)->assertOk()->assertJsonPath('data.notes', '59');
    }

    public function test_upload_attempts_share_a_limit_across_pose_urls_and_do_not_block_check_in_saves(): void
    {
        $client = User::factory()->coached()->create();
        $check = $client->dailyCheckIns()->create(['check_in_date' => today()]);
        $base = '/api/clients/'.$client->id.'/daily-check-ins/'.$check->id;
        $this->actingAs($client);
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $pose = $attempt % 2 ? 'front_relaxed' : 'rear_relaxed';
            $this->postJson($base.'/posing/photos/'.$pose, ['photo_version' => 0])->assertUnprocessable()->assertJsonValidationErrors('photo');
        }
        $this->postJson($base.'/posing/photos/favourite_classic_pose', ['photo_version' => 0])->assertTooManyRequests();
        $this->assertDatabaseCount('progress_photos', 0);
        $this->patchJson($base, ['notes' => 'Saved without a photo'])->assertOk();
    }

    public function test_report_limit_does_not_consume_an_unopened_temporary_handover(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        $attributes = ['coach_id' => $coach->id, 'client_id' => $client->id, 'started_on' => today(), 'ended_at' => now(),
            'snapshot' => ['coach_name' => $coach->name, 'discipline' => 'Bodybuilding'], 'pdf_base64' => base64_encode('%PDF-test')];
        $permanent = CoachingHandover::create($attributes);
        $temporary = CoachingHandover::create([...$attributes, 'delete_after_view' => true]);
        $this->actingAs($client);
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->get('/api/coaching-handovers/'.$permanent->id.'/pdf')->assertOk();
        }
        $this->postJson('/api/coaching-handovers/'.$temporary->id.'/open')->assertTooManyRequests();
        $this->assertNull($temporary->fresh()->opened_at);
        $this->assertDatabaseCount('coaching_handovers', 2);
        $this->getJson('/api/coaching-handovers')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_analytics_share_a_limit_without_blocking_other_dashboard_reads(): void
    {
        $client = User::factory()->coached()->create();
        $base = '/api/clients/'.$client->id;
        $this->actingAs($client);
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->getJson($base.'/bodyweight-trend')->assertOk();
        }
        $this->getJson($base.'/exercise-progress?exercise_id=1')->assertTooManyRequests();
        $this->getJson($base.'/workouts')->assertOk();
    }

    public function test_billing_actions_share_a_budget_before_calling_stripe_again(): void
    {
        $coach = User::factory()->create(['role' => 'coach', 'billing_exempt' => false]);
        $stripe = Mockery::mock(StripeGateway::class);
        $stripe->allows('configured')->andReturn(true);
        $this->app->instance(StripeGateway::class, $stripe);
        $billing = Mockery::mock(CoachBilling::class);
        $billing->expects('checkout')->with(Mockery::on(fn (User $user) => $user->id === $coach->id))->times(10)
            ->andReturn('https://checkout.stripe.com/test');
        $this->app->instance(CoachBilling::class, $billing);
        $this->actingAs($coach);
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/billing/checkout')->assertOk();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.23'])
            ->postJson('/api/billing/cancel', ['confirmed' => true])->assertTooManyRequests();
        $this->assertDatabaseCount('coach_payments', 0);
        $this->assertDatabaseCount('coach_memberships', 0);
    }
}
