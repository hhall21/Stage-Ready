<?php

namespace Tests\Feature;

use App\Models\CoachingHandover;
use App\Models\CoachMembership;
use App\Models\ProgressPhoto;
use App\Models\User;
use App\Services\CoachingHandoverPdf;
use App\Services\ExpiredCoachData;
use App\Services\StripeGateway;
use App\Services\TemporaryPosePhotos;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CoachRetentionTest extends TestCase
{
    use DatabaseMigrations;

    private $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'services.stripe.prices.powerlifting' => 'price_powerlifting', 'services.stripe.prices.bodybuilding' => 'price_bodybuilding']);
        Storage::fake('checkin_photos');
        Storage::fake('temporary_checkin_photos');
        Storage::fake('local');
        $this->stripe = Mockery::mock(StripeGateway::class);
        $this->stripe->allows('configured')->andReturn(true);
        $this->app->instance(StripeGateway::class, $this->stripe);
        $this->mock(CoachingHandoverPdf::class)->allows('render')->andReturn('%PDF-1.4 Test handover');
    }

    public function test_after_seven_days_handover_is_created_before_records_and_media_are_deleted_and_accounts_survive(): void
    {
        [$coach, $membership, $clients] = $this->state();
        $client = $clients[0];
        $check = $client->dailyCheckIns()->firstOrFail();
        $path = $client->id.'/'.$check->id.'/pose.jpg';
        ProgressPhoto::create(['check_in_id' => $check->id, 'photo_type' => 'front', 'pose_key' => 'front_relaxed', 'file_path' => $path, 'mime_type' => 'image/jpeg']);
        Storage::disk('checkin_photos')->put($path, 'private photo');
        $temporary = app(TemporaryPosePhotos::class);
        $temporary->locked($client->id, $check->id, fn () => DB::transaction(function () use ($temporary, $check): void {
            $temporary->store($check, 'most_muscular', UploadedFile::fake()->image('pose.png', 10, 10));
            $check->forceFill(['photo_version' => $check->photo_version + 1])->save();
        }));
        $this->assertCount(2, Storage::disk('temporary_checkin_photos')->allFiles());
        $clip = 'clips/'.$client->id.'/clip.mp4';
        DB::table('training_clips')->insert(['client_id' => $client->id, 'file_path' => $clip, 'uploaded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        Storage::disk('local')->put($clip, 'private clip');
        $this->expectStripe($coach, $membership);
        $this->artisan('coaching:expire-unpaid')->assertExitCode(0);
        $report = CoachingHandover::firstOrFail();
        $this->assertTrue($report->delete_after_view);
        $this->assertCount(1, $report->snapshot['weights']);
        $this->assertSame(1, $report->snapshot['completed_sessions']);
        $this->assertSame($membership->paid_through->toDateString(), $report->snapshot['end_date']);
        $this->assertDatabaseCount('daily_check_ins', 0);
        $this->assertDatabaseCount('workouts', 0);
        $this->assertDatabaseCount('progress_photos', 0);
        $this->assertDatabaseCount('training_clips', 0);
        Storage::disk('checkin_photos')->assertMissing($path);
        $this->assertSame([], Storage::disk('temporary_checkin_photos')->allFiles());
        Storage::disk('local')->assertMissing($clip);
        $this->assertDatabaseHas('users', ['id' => $client->id, 'is_active' => true]);
        $this->assertDatabaseHas('users', ['id' => $coach->id, 'is_active' => true]);
        $this->assertDatabaseHas('trainer_clients', ['trainer_id' => $coach->id, 'client_id' => $client->id, 'status' => 'inactive']);
        $this->assertNotNull($membership->fresh()->data_purged_at);
        $this->artisan('coaching:expire-unpaid')->assertExitCode(0);
        $this->assertDatabaseCount('coaching_handovers', 1);
    }

    public function test_within_retention_window_access_is_blocked_but_data_is_not_deleted(): void
    {
        [$coach, $membership, $clients] = $this->state(6);
        $this->artisan('coaching:expire-unpaid')->assertExitCode(0);
        $this->assertDatabaseCount('daily_check_ins', 1);
        $this->assertDatabaseCount('coaching_handovers', 0);
        $this->assertNull($membership->fresh()->data_purged_at);
        $this->actingAs($clients[0])->getJson('/api/clients/'.$clients[0]->id.'/workouts')->assertStatus(402);
        $this->actingAs($coach)->getJson('/api/clients')->assertStatus(402);
    }

    public function test_reconciliation_recovers_a_missed_payment_webhook_before_any_deletion(): void
    {
        [$coach, $membership] = $this->state();
        $this->expectStripe($coach, $membership, now()->addMonth());
        $this->artisan('coaching:expire-unpaid')->assertExitCode(0);
        $this->assertTrue($coach->hasPaidCoachAccess());
        $this->assertDatabaseCount('daily_check_ins', 1);
        $this->assertDatabaseCount('coaching_handovers', 0);
        $this->assertNull($membership->fresh()->data_purged_at);
    }

    public function test_stripe_outage_defers_cleanup_without_deleting_data(): void
    {
        [$coach, $membership] = $this->state();
        $this->stripe->expects('paidInvoices')->with('cus_'.$coach->id)->andThrow(new RuntimeException('Stripe unavailable'));
        $this->artisan('coaching:expire-unpaid')->assertExitCode(1);
        $this->assertDatabaseCount('daily_check_ins', 1);
        $this->assertDatabaseCount('coaching_handovers', 0);
        $this->assertNull($membership->fresh()->data_purged_at);
    }

    public function test_another_active_coach_protects_shared_client_data(): void
    {
        [$coach, $membership, $clients] = $this->state();
        $other = User::factory()->create(['role' => 'coach']);
        $other->clients()->attach($clients[0], ['status' => 'active', 'start_date' => today()->subMonth()->toDateString()]);
        $this->expectStripe($coach, $membership);
        $this->artisan('coaching:expire-unpaid')->assertExitCode(0);
        $this->assertDatabaseCount('daily_check_ins', 1);
        $this->assertDatabaseCount('workouts', 1);
        $this->assertDatabaseCount('coaching_handovers', 1);
        $this->assertTrue($clients[0]->hasActiveCoach());
        $this->actingAs($clients[0])->getJson('/api/clients/'.$clients[0]->id.'/workouts')->assertOk();
    }

    public function test_pdf_failure_rolls_back_every_client_and_handover_in_the_batch(): void
    {
        [$coach, $membership] = $this->state(8, 2);
        $this->expectStripe($coach, $membership);
        $calls = 0;
        $this->mock(CoachingHandoverPdf::class)->expects('render')->twice()->andReturnUsing(function () use (&$calls) {
            if (++$calls === 2) {
                throw new RuntimeException('PDF rendering failed');
            }

            return '%PDF-1.4 First report';
        });
        $this->artisan('coaching:expire-unpaid')->assertExitCode(1);
        $this->assertDatabaseCount('daily_check_ins', 2);
        $this->assertDatabaseCount('workouts', 2);
        $this->assertDatabaseCount('coaching_handovers', 0);
        $this->assertSame(2, DB::table('trainer_clients')->where('status', 'active')->count());
        $this->assertNull($membership->fresh()->data_purged_at);
    }

    public function test_handover_is_retained_until_owner_opens_and_acknowledges_loading_it(): void
    {
        [$coach, $membership, $clients] = $this->state();
        $this->expectStripe($coach, $membership);
        app(ExpiredCoachData::class)->process($membership);
        $handover = CoachingHandover::firstOrFail();
        $base = '/api/coaching-handovers/'.$handover->id;
        $this->actingAs(User::factory()->create())->postJson($base.'/open')->assertForbidden();
        $this->actingAs($clients[0])->getJson('/api/coaching-handovers')->assertOk()->assertJsonPath('data.0.delete_after_view', true);
        $this->assertDatabaseHas('coaching_handovers', ['id' => $handover->id]);
        $this->get($base.'/pdf')->assertForbidden();
        $opened = $this->postJson($base.'/open')->assertOk()->assertJsonPath('pdf_base64', base64_encode('%PDF-1.4 Test handover'));
        $this->assertDatabaseHas('coaching_handovers', ['id' => $handover->id]);
        $this->postJson($base.'/viewed', ['view_token' => str_repeat('x', 64)])->assertForbidden();
        $this->postJson($base.'/viewed', ['view_token' => $opened->json('view_token')])->assertNoContent();
        $this->assertDatabaseMissing('coaching_handovers', ['id' => $handover->id]);
        $this->assertDatabaseHas('users', ['id' => $clients[0]->id]);
        $this->getJson('/api/coaching-handovers')->assertJsonCount(0, 'data');
    }

    public function test_dry_run_and_unpaid_accounts_without_a_recorded_paid_period_never_purge_data(): void
    {
        [, $membership] = $this->state();
        $this->artisan('coaching:expire-unpaid', ['--dry-run' => true])->assertExitCode(0);
        $this->assertDatabaseCount('daily_check_ins', 1);
        $membership->update(['last_paid_at' => null, 'paid_through' => null]);
        $this->artisan('coaching:expire-unpaid')->assertExitCode(0);
        $this->assertDatabaseCount('daily_check_ins', 1);
    }

    private function state(int $daysOverdue = 8, int $count = 1): array
    {
        $coach = User::factory()->create(['role' => 'coach', 'billing_exempt' => false]);
        $end = now()->subDays($daysOverdue);
        $membership = CoachMembership::create(['user_id' => $coach->id, 'stripe_customer_id' => 'cus_'.$coach->id,
            'stripe_subscription_id' => 'sub_'.$coach->id, 'status' => 'past_due', 'last_paid_at' => $end->subMonth(),
            'paid_through' => $end, 'retention_expires_at' => $end->addDays(7)]);
        $clients = [];
        for ($index = 0; $index < $count; $index++) {
            $client = User::factory()->create();
            $coach->clients()->attach($client, ['status' => 'active', 'start_date' => $end->subMonth()->toDateString()]);
            $client->dailyCheckIns()->create(['check_in_date' => $end->subDay()->toDateString(), 'bodyweight' => 80]);
            $client->workouts()->create(['workout_date' => $end->subDay()->toDateString(), 'workout_name' => 'Completed session', 'completed' => true]);
            $clients[] = $client;
        }

        return [$coach, $membership, $clients];
    }

    private function expectStripe(User $coach, CoachMembership $membership, $renewedEnd = null): void
    {
        $amount = $coach->is_bodybuilding_coach ? 800 : 600;
        $priceId = $coach->is_bodybuilding_coach ? 'price_bodybuilding' : 'price_powerlifting';
        $end = $renewedEnd ?? $membership->paid_through;
        $start = $renewedEnd ? now() : $membership->last_paid_at;
        $this->stripe->allows('subscription')->with('sub_'.$coach->id)->andReturn(['id' => 'sub_'.$coach->id,
            'customer' => 'cus_'.$coach->id, 'metadata' => ['coach_id' => (string) $coach->id], 'status' => 'active']);
        $this->stripe->expects('paidInvoices')->with('cus_'.$coach->id)->once()->andReturn([[
            'id' => 'in_'.$coach->id, 'status' => 'paid', 'amount_paid' => $amount, 'currency' => 'gbp', 'customer' => 'cus_'.$coach->id,
            'subscription' => 'sub_'.$coach->id, 'status_transitions' => ['paid_at' => $start->timestamp],
            'lines' => ['data' => [['amount' => $amount, 'quantity' => 1, 'price' => ['id' => $priceId],
                'period' => ['start' => $start->timestamp, 'end' => $end->timestamp]]]],
        ]]);
    }
}
