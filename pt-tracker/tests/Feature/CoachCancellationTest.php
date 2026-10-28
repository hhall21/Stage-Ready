<?php

namespace Tests\Feature;

use App\Models\CoachMembership;
use App\Models\User;
use App\Services\CoachBilling;
use App\Services\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CoachCancellationTest extends TestCase
{
    use RefreshDatabase;

    private $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->stripe = Mockery::mock(StripeGateway::class);
        $this->stripe->allows('configured')->andReturn(true);
        $this->app->instance(StripeGateway::class, $this->stripe);
    }

    public function test_only_active_coach_accounts_can_open_the_page_and_unpaid_coaches_can_still_cancel(): void
    {
        $this->get('/subscription/cancel')->assertRedirect('/login');
        $this->postJson('/api/billing/cancel', ['confirmed' => true])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->get('/subscription/cancel')->assertForbidden();
        $this->postJson('/api/billing/cancel', ['confirmed' => true])->assertForbidden();
        $coach = $this->coach();
        $this->membership($coach, now()->subDay());
        $this->actingAs($coach)->withoutVite()->get('/subscription/cancel')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('CancelSubscription'));
        $this->getJson('/api/billing')->assertJsonPath('data.has_subscription', true)->assertJsonPath('data.has_access', false);
        $coach->forceFill(['is_active' => false])->save();
        $this->get('/subscription/cancel')->assertForbidden();
        $this->assertGuest();
    }

    public function test_confirmed_cancellation_targets_only_the_owners_subscription_and_preserves_access_dates_and_client_records(): void
    {
        $coach = $this->coach();
        $membership = $this->membership($coach);
        $dates = $membership->only(['paid_through', 'last_paid_at', 'retention_expires_at']);
        $client = User::factory()->create();
        $coach->clients()->attach($client, ['status' => 'active', 'start_date' => today()->toDateString()]);
        $check = $client->dailyCheckIns()->create(['check_in_date' => today(), 'bodyweight' => 80]);
        $this->stripe->expects('subscription')->with('sub_'.$coach->id)->andReturn($this->subscription($coach));
        $this->stripe->expects('cancelAtPeriodEnd')->with('sub_'.$coach->id)->andReturn($this->subscription($coach, true));
        $this->actingAs($coach)->postJson('/api/billing/cancel', ['confirmed' => true, 'subscription_id' => 'sub_somebody_else'])
            ->assertOk()->assertJsonPath('data.cancel_at_period_end', true)->assertJsonPath('data.has_access', true);
        $this->assertEquals($dates, $membership->fresh()->only(['paid_through', 'last_paid_at', 'retention_expires_at']));
        $this->assertTrue($client->hasActiveCoach());
        $this->assertDatabaseHas('daily_check_ins', ['id' => $check->id]);
        $this->assertDatabaseCount('coaching_handovers', 0);
        $this->assertDatabaseCount('coach_payments', 0);
        $this->getJson('/api/clients')->assertOk();
        app(CoachBilling::class)->webhook(['id' => 'evt_before_cancellation', 'type' => 'customer.subscription.updated',
            'created' => now()->subHour()->timestamp, 'data' => ['object' => $this->subscription($coach)]]);
        $this->assertTrue($membership->fresh()->cancel_at_period_end);
    }

    public function test_repeated_confirmation_is_idempotent_and_already_ended_subscriptions_are_not_updated(): void
    {
        $coach = $this->coach();
        $membership = $this->membership($coach);
        $this->stripe->expects('subscription')->with('sub_'.$coach->id)->times(3)
            ->andReturn($this->subscription($coach), $this->subscription($coach, true), $this->subscription($coach, false, 'canceled'));
        $this->stripe->expects('cancelAtPeriodEnd')->with('sub_'.$coach->id)->once()->andReturn($this->subscription($coach, true));
        $this->actingAs($coach)->postJson('/api/billing/cancel', ['confirmed' => true])->assertOk()->assertJsonPath('data.cancel_at_period_end', true);
        $this->postJson('/api/billing/cancel', ['confirmed' => true])->assertOk()->assertJsonPath('data.cancel_at_period_end', true);
        $this->postJson('/api/billing/cancel', ['confirmed' => true])->assertOk()->assertJsonPath('data.status', 'canceled');
        $this->assertFalse($membership->fresh()->cancel_at_period_end);
        $this->assertTrue($coach->hasPaidCoachAccess());
    }

    public function test_unconfirmed_and_missing_subscription_requests_never_change_stripe(): void
    {
        $coach = $this->coach();
        $this->actingAs($coach)->postJson('/api/billing/cancel')->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->postJson('/api/billing/cancel', ['confirmed' => false])->assertUnprocessable();
        $this->postJson('/api/billing/cancel', ['confirmed' => true])->assertConflict();
        $this->getJson('/api/billing')->assertJsonPath('data.has_subscription', false);
    }

    public function test_provider_ownership_mismatch_is_rejected_before_cancellation(): void
    {
        $coach = $this->coach();
        $membership = $this->membership($coach);
        $this->stripe->expects('subscription')->with('sub_'.$coach->id)->andReturn([
            ...$this->subscription($coach), 'customer' => 'cus_somebody_else', 'metadata' => ['coach_id' => '999'],
        ]);
        $this->actingAs($coach)->postJson('/api/billing/cancel', ['confirmed' => true])->assertForbidden();
        $this->assertFalse($membership->fresh()->cancel_at_period_end);
    }

    public function test_stripe_failure_does_not_claim_cancellation_or_change_membership_dates(): void
    {
        $coach = $this->coach();
        $membership = $this->membership($coach);
        $end = $membership->paid_through;
        $this->stripe->expects('subscription')->with('sub_'.$coach->id)->andReturn($this->subscription($coach));
        $this->stripe->expects('cancelAtPeriodEnd')->with('sub_'.$coach->id)->andThrow(new RuntimeException('Stripe unavailable'));
        $this->actingAs($coach)->postJson('/api/billing/cancel', ['confirmed' => true])->assertServerError();
        $this->assertFalse($membership->fresh()->cancel_at_period_end);
        $this->assertTrue($end->equalTo($membership->fresh()->paid_through));
    }

    public function test_cancellation_requires_csrf_and_cannot_run_without_payment_configuration(): void
    {
        $coach = $this->coach();
        $this->membership($coach);
        $this->actingAs($coach);
        $this->app['env'] = 'local';
        $this->withSession(['_token' => 'cancel-csrf-token'])->postJson('/api/billing/cancel', ['confirmed' => true])->assertStatus(419);
        $this->app->instance(StripeGateway::class, new StripeGateway);
        config(['services.stripe.secret' => null]);
        $this->withHeader('X-CSRF-TOKEN', 'cancel-csrf-token')->postJson('/api/billing/cancel', ['confirmed' => true])->assertStatus(503);
    }

    private function coach(): User
    {
        return User::factory()->create(['role' => 'coach', 'billing_exempt' => false]);
    }

    private function membership(User $coach, $end = null): CoachMembership
    {
        $end = ($end ?? now()->addMonth())->toImmutable();

        return CoachMembership::create(['user_id' => $coach->id, 'stripe_customer_id' => 'cus_'.$coach->id,
            'stripe_subscription_id' => 'sub_'.$coach->id, 'status' => 'active', 'last_paid_at' => now()->subDay(),
            'paid_through' => $end, 'retention_expires_at' => $end->addDays(7)]);
    }

    private function subscription(User $coach, bool $scheduled = false, string $status = 'active'): array
    {
        return ['id' => 'sub_'.$coach->id, 'customer' => 'cus_'.$coach->id, 'metadata' => ['coach_id' => (string) $coach->id],
            'status' => $status, 'cancel_at_period_end' => $scheduled];
    }
}
