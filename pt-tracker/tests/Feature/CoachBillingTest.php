<?php

namespace Tests\Feature;

use App\Models\CoachMembership;
use App\Models\User;
use App\Services\CoachBilling;
use App\Services\StripeGateway;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CoachBillingTest extends TestCase
{
    use RefreshDatabase;

    private $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.url' => 'http://localhost',
            'services.stripe.prices.powerlifting' => 'price_powerlifting', 'services.stripe.prices.bodybuilding' => 'price_bodybuilding',
            'services.stripe.webhook_secret' => 'whsec_test', 'services.stripe.live_mode' => false]);
        Notification::fake();
        $this->stripe = Mockery::mock(StripeGateway::class);
        $this->stripe->allows('configured')->andReturn(true);
        $this->app->instance(StripeGateway::class, $this->stripe);
    }

    public function test_anyone_can_register_as_an_unpaid_coach_but_cannot_choose_privileges(): void
    {
        $this->postJson('/api/register', ['name' => 'Public Coach', 'email' => 'PUBLIC@example.com',
            'password' => 'PublicCoachPassword123', 'password_confirmation' => 'PublicCoachPassword123',
            'is_bodybuilding_coach' => false, 'is_admin' => true, 'billing_exempt' => true, 'role' => 'client'])->assertCreated();
        $coach = User::where('email', 'public@example.com')->firstOrFail();
        $this->assertSame('coach', $coach->role);
        $this->assertFalse($coach->is_admin);
        $this->assertFalse($coach->billing_exempt);
        $this->assertFalse($coach->is_bodybuilding_coach);
        $this->assertFalse($coach->hasVerifiedEmail());
        $this->assertAuthenticatedAs($coach);
        Notification::assertSentTo($coach, VerifyEmail::class);
        $this->get('/dashboard')->assertRedirect('/email/verify');
        $this->postJson('/api/client-invitations', ['name' => 'Client', 'email' => 'client@example.com'])->assertStatus(402);
        $this->postJson('/api/billing/checkout')->assertForbidden();
        $this->getJson('/api/billing')->assertJsonPath('data.amount_pence', 600)->assertJsonPath('data.currency', 'gbp')->assertJsonPath('data.has_access', false);
    }

    public static function coachPlans(): array
    {
        return ['powerlifting' => [false, 600, 'price_powerlifting', 'powerlifting'],
            'bodybuilding' => [true, 800, 'price_bodybuilding', 'bodybuilding']];
    }

    public function test_public_pages_share_both_monthly_prices(): void
    {
        $this->withoutVite()->get('/register')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Register')->where('coachPlans.powerlifting.amount_pence', 600)
            ->where('coachPlans.powerlifting.price_label', '£6')
            ->where('coachPlans.bodybuilding.amount_pence', 800)
            ->where('coachPlans.bodybuilding.price_label', '£8'));
    }

    #[DataProvider('coachPlans')]
    public function test_each_discipline_has_its_own_verified_monthly_price(bool $bodybuilding, int $amount, string $priceId, string $discipline): void
    {
        $coach = $this->coach($bodybuilding);
        $this->membership($coach, now()->subDay());
        $this->subscription($coach);
        $this->actingAs($coach)->getJson('/api/billing')->assertOk()
            ->assertJsonPath('data.amount_pence', $amount)->assertJsonPath('data.discipline', $discipline);
        $invoice = $this->invoice($coach, now(), now()->addMonth());
        $this->assertSame($priceId, $invoice['lines']['data'][0]['pricing']['price_details']['price']);
        $this->assertTrue(app(CoachBilling::class)->recordInvoice($invoice));
        $this->assertDatabaseHas('coach_payments', ['stripe_invoice_id' => 'in_paid', 'amount_pence' => $amount, 'currency' => 'gbp']);
        $this->assertTrue($coach->hasPaidCoachAccess());
    }

    #[DataProvider('coachPlans')]
    public function test_another_disciplines_price_or_amount_never_unlocks_access(bool $bodybuilding, int $amount, string $priceId, string $discipline): void
    {
        $coach = $this->coach($bodybuilding);
        $this->membership($coach, now()->subDay());
        $this->subscription($coach);
        $invoice = $this->invoice($coach, now(), now()->addMonth());
        $wrongPrice = $bodybuilding ? 'price_powerlifting' : 'price_bodybuilding';
        $wrongAmount = $bodybuilding ? 600 : 800;
        $invoice['lines']['data'][0]['pricing']['price_details']['price'] = $wrongPrice;
        $this->assertFalse(app(CoachBilling::class)->recordInvoice($invoice));
        $invoice['lines']['data'][0]['pricing']['price_details']['price'] = $priceId;
        $invoice['amount_paid'] = $wrongAmount;
        $this->assertFalse(app(CoachBilling::class)->recordInvoice($invoice));
        $invoice['amount_paid'] = $amount;
        $invoice['lines']['data'][0]['amount'] = $wrongAmount;
        $this->assertFalse(app(CoachBilling::class)->recordInvoice($invoice));
        $invoice['lines']['data'][0]['amount'] = $amount;
        config(['services.stripe.prices.'.$discipline => null]);
        $this->assertFalse(app(CoachBilling::class)->recordInvoice($invoice));
        $this->assertFalse($coach->hasPaidCoachAccess());
        $this->assertDatabaseCount('coach_payments', 0);
    }

    public function test_registration_validates_passwords_and_duplicate_email_and_verification_links_are_signed(): void
    {
        $this->postJson('/api/register', ['name' => '', 'email' => 'invalid', 'password' => 'weak'])->assertUnprocessable();
        $coach = $this->coach();
        $this->postJson('/api/register', ['name' => 'Duplicate', 'email' => strtoupper($coach->email),
            'password' => 'ValidPassword123', 'password_confirmation' => 'ValidPassword123', 'is_bodybuilding_coach' => true])->assertUnprocessable()->assertJsonValidationErrors('email');
        $coach->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($coach)->get('/email/verify/'.$coach->id.'/'.sha1($coach->email))->assertForbidden();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $coach->id, 'hash' => sha1($coach->email)]);
        $this->get($url)->assertRedirect('/payment');
        $this->assertTrue($coach->fresh()->hasVerifiedEmail());
    }

    public function test_paid_access_expires_for_existing_sessions_and_clients_but_billing_and_handovers_stay_available(): void
    {
        $coach = $this->coach();
        $this->membership($coach, now()->addHour());
        $client = User::factory()->create();
        $coach->clients()->attach($client, ['status' => 'active', 'start_date' => today()->toDateString()]);
        $this->assertTrue($client->hasActiveCoach());
        $this->actingAs($coach)->getJson('/api/clients')->assertOk();
        $this->travel(2)->hours();
        $this->getJson('/api/clients')->assertStatus(402);
        $this->get('/dashboard')->assertRedirect('/payment');
        $this->getJson('/api/billing')->assertOk()->assertJsonPath('data.has_access', false);
        $this->actingAs($client)->getJson('/api/clients/'.$client->id.'/daily-check-ins')->assertStatus(402);
        $this->postJson('/api/clients/'.$client->id.'/daily-check-ins', [])->assertStatus(402);
        $this->getJson('/api/user')->assertJsonPath('data.has_active_coaching', false)->assertJsonPath('data.has_suspended_coaching', true);
        $this->getJson('/api/coaching-handovers')->assertOk();
        $this->withoutVite()->get('/dashboard')->assertOk();
        $this->postJson('/api/logout')->assertOk();
        $this->postJson('/api/login', ['email' => $coach->email, 'password' => 'password'])->assertOk();
    }

    public function test_a_second_paid_coach_keeps_client_access_available(): void
    {
        $expired = $this->coach();
        $this->membership($expired, now()->subDay());
        $other = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        foreach ([$expired, $other] as $coach) {
            $coach->clients()->attach($client, ['status' => 'active', 'start_date' => today()->subDays(4)->toDateString()]);
        }
        $this->actingAs($client)->getJson('/api/clients/'.$client->id.'/daily-check-ins')->assertOk();
        $this->assertTrue($client->hasActiveCoach());
        $this->assertFalse($client->hasSuspendedCoaching());
    }

    public function test_verified_payment_records_exact_time_and_stripe_period_and_old_events_cannot_shorten_access(): void
    {
        $coach = $this->coach();
        $member = $this->membership($coach, now()->subDay());
        $this->subscription($coach);
        $end = now()->addMonthNoOverflow();
        $invoice = $this->invoice($coach, now(), $end);
        $billing = app(CoachBilling::class);
        $this->assertTrue($billing->recordInvoice($invoice));
        $this->assertTrue($billing->recordInvoice($invoice));
        $this->assertDatabaseCount('coach_payments', 1);
        $this->assertSame($end->timestamp, $member->fresh()->paid_through->timestamp);
        $this->assertSame(now()->timestamp, $member->fresh()->last_paid_at->timestamp);
        $this->assertSame($end->addDays(7)->timestamp, $member->fresh()->retention_expires_at->timestamp);
        $older = $this->invoice($coach, now()->subMonths(2), now()->subMonth(), 'in_older');
        $billing->recordInvoice($older);
        $this->assertSame($end->timestamp, $member->fresh()->paid_through->timestamp);
        $this->assertTrue($coach->hasPaidCoachAccess());
    }

    public function test_wrong_amount_currency_price_customer_or_unpaid_invoice_never_grants_access(): void
    {
        $coach = $this->coach();
        $this->membership($coach, now()->subDay());
        $this->subscription($coach);
        $invoice = $this->invoice($coach, now(), now()->addMonth());
        foreach ([['amount_paid' => 1], ['currency' => 'usd'], ['status' => 'open'], ['customer' => 'cus_wrong']] as $change) {
            $this->assertFalse(app(CoachBilling::class)->recordInvoice([...$invoice, ...$change]));
        }
        $invoice['lines']['data'][0]['pricing']['price_details']['price'] = 'price_wrong';
        $this->assertFalse(app(CoachBilling::class)->recordInvoice($invoice));
        $this->assertFalse($coach->hasPaidCoachAccess());
        $this->assertDatabaseCount('coach_payments', 0);
    }

    public function test_checkout_reuses_an_open_session_instead_of_creating_duplicate_subscriptions(): void
    {
        $coach = $this->coach();
        $this->stripe->expects('createCustomer')->once()->andReturn('cus_'.$coach->id);
        $session = ['id' => 'cs_test_open', 'url' => 'https://checkout.stripe.com/test', 'expires_at' => now()->addHour()->timestamp, 'status' => 'open'];
        $this->stripe->expects('createCheckout')->once()->andReturn($session);
        $this->stripe->expects('checkout')->with('cs_test_open')->once()->andReturn($session);
        $this->actingAs($coach)->postJson('/api/billing/checkout')->assertOk()->assertJsonPath('url', $session['url']);
        $this->postJson('/api/billing/checkout')->assertOk()->assertJsonPath('url', $session['url']);
        $this->assertDatabaseCount('coach_memberships', 1);
    }

    public function test_checkout_ignores_browser_supplied_price_and_discipline(): void
    {
        $coach = $this->coach();
        $this->stripe->expects('createCustomer')->with(Mockery::on(fn (User $user) => $user->id === $coach->id))->andReturn('cus_'.$coach->id);
        $this->stripe->expects('createCheckout')->with(Mockery::on(fn (User $user) => $user->id === $coach->id && $user->is_bodybuilding_coach), 'cus_'.$coach->id)
            ->andReturn(['id' => 'cs_test_plan', 'url' => 'https://checkout.stripe.com/test', 'expires_at' => now()->addHour()->timestamp]);
        $this->actingAs($coach)->postJson('/api/billing/checkout', ['price_id' => 'price_powerlifting', 'amount_pence' => 1, 'is_bodybuilding_coach' => false])->assertOk();
        $this->assertTrue($coach->fresh()->is_bodybuilding_coach);
        $this->assertFalse($coach->hasPaidCoachAccess());
    }

    public function test_an_existing_subscription_uses_the_customer_portal_for_recovery(): void
    {
        $coach = $this->coach();
        $this->membership($coach, now()->subDay());
        $this->subscription($coach, 'past_due');
        $this->stripe->expects('portal')->with('cus_'.$coach->id)->andReturn('https://billing.stripe.com/recover');
        $this->actingAs($coach)->postJson('/api/billing/checkout')->assertOk()->assertJsonPath('url', 'https://billing.stripe.com/recover');
    }

    public function test_server_confirmation_can_recover_a_successful_checkout_before_the_webhook_arrives(): void
    {
        $coach = $this->coach();
        $this->membership($coach, now()->subDay());
        $this->stripe->expects('checkout')->with('cs_test_paid')->andReturn(['mode' => 'subscription',
            'client_reference_id' => (string) $coach->id, 'customer' => 'cus_'.$coach->id,
            'payment_status' => 'paid', 'subscription' => 'sub_'.$coach->id]);
        $this->stripe->allows('subscription')->with('sub_'.$coach->id)->andReturn(['id' => 'sub_'.$coach->id,
            'customer' => 'cus_'.$coach->id, 'metadata' => ['coach_id' => (string) $coach->id], 'status' => 'active', 'latest_invoice' => 'in_return']);
        $this->stripe->expects('invoice')->with('in_return')->andReturn($this->invoice($coach, now(), now()->addMonth(), 'in_return'));
        $this->actingAs($coach)->postJson('/api/billing/confirm', ['session_id' => 'cs_test_paid'])->assertOk()->assertJsonPath('data.has_access', true);
        $this->assertDatabaseCount('coach_payments', 1);
    }

    public function test_mode_mismatches_and_missing_configuration_disable_checkout(): void
    {
        $gateway = new StripeGateway;
        config(['services.stripe.secret' => 'sk_test_example', 'services.stripe.live_mode' => true]);
        $this->assertFalse($gateway->configured());
        config(['services.stripe.live_mode' => false]);
        $this->assertTrue($gateway->configured());
        $bodybuilding = $this->coach();
        $powerlifting = $this->coach(false);
        config(['services.stripe.prices.bodybuilding' => null]);
        $this->assertFalse($gateway->configured($bodybuilding));
        $this->assertTrue($gateway->configured($powerlifting));
        $this->assertTrue($gateway->configured());
        config(['services.stripe.secret' => null]);
        $this->app->instance(StripeGateway::class, $gateway);
        $this->actingAs($this->coach())->postJson('/api/billing/checkout')->assertStatus(503);
    }

    public function test_success_return_cannot_confirm_somebody_elses_checkout_or_a_browser_success_flag(): void
    {
        $coach = $this->coach();
        $this->membership($coach, now()->subDay());
        $this->stripe->expects('checkout')->with('cs_test_wrong')->andReturn(['mode' => 'subscription', 'customer' => 'cus_wrong', 'client_reference_id' => '999', 'payment_status' => 'paid']);
        $this->actingAs($coach)->postJson('/api/billing/confirm', ['session_id' => 'cs_test_wrong', 'success' => true])->assertForbidden();
        $this->postJson('/api/billing/confirm', ['success' => true])->assertUnprocessable();
        $this->assertFalse($coach->hasPaidCoachAccess());
    }

    public function test_stripe_webhooks_require_signatures_are_replay_safe_and_reject_wrong_environment(): void
    {
        $coach = $this->coach();
        $this->membership($coach, now()->subDay());
        $this->subscription($coach);
        $this->stripe->expects('invoice')->with('in_paid')->once()->andReturn($this->invoice($coach, now(), now()->addMonth()));
        $event = ['id' => 'evt_paid', 'object' => 'event', 'type' => 'invoice.paid', 'created' => now()->timestamp, 'livemode' => false,
            'data' => ['object' => ['id' => 'in_paid']]];
        $this->signedWebhook($event)->assertOk();
        $this->signedWebhook($event)->assertOk();
        $this->assertDatabaseCount('coach_payments', 1);
        $this->assertDatabaseCount('stripe_webhook_events', 1);
        $this->postJson('/stripe/webhook', $event)->assertBadRequest();
        $this->signedWebhook([...$event, 'id' => 'evt_wrong_mode', 'livemode' => true])->assertBadRequest();
    }

    public function test_cancellation_preserves_paid_through_access_and_old_status_events_do_not_overwrite_newer_state(): void
    {
        $coach = $this->coach();
        $member = $this->membership($coach, now()->addDay());
        $base = ['object' => 'event', 'type' => 'customer.subscription.updated', 'created' => now()->timestamp, 'livemode' => false,
            'data' => ['object' => ['id' => 'sub_'.$coach->id, 'status' => 'canceled', 'cancel_at_period_end' => true]]];
        $this->signedWebhook(['id' => 'evt_cancel', ...$base])->assertOk();
        $this->signedWebhook(['id' => 'evt_old', ...$base, 'created' => now()->subHour()->timestamp,
            'data' => ['object' => ['id' => 'sub_'.$coach->id, 'status' => 'active']]])->assertOk();
        $this->assertSame('canceled', $member->fresh()->status);
        $this->assertTrue($coach->hasPaidCoachAccess());
    }

    private function coach(bool $bodybuilding = true): User
    {
        return User::factory()->create(['role' => 'coach', 'billing_exempt' => false, 'is_bodybuilding_coach' => $bodybuilding]);
    }

    private function membership(User $coach, $end): CoachMembership
    {
        return CoachMembership::create(['user_id' => $coach->id, 'stripe_customer_id' => 'cus_'.$coach->id,
            'stripe_subscription_id' => 'sub_'.$coach->id, 'status' => 'active', 'last_paid_at' => $end->subMonth(),
            'paid_through' => $end, 'retention_expires_at' => $end->addDays(7)]);
    }

    private function subscription(User $coach, string $status = 'active'): void
    {
        $this->stripe->allows('subscription')->with('sub_'.$coach->id)->andReturn(['id' => 'sub_'.$coach->id, 'status' => $status,
            'customer' => 'cus_'.$coach->id, 'metadata' => ['coach_id' => (string) $coach->id]]);
    }

    private function invoice(User $coach, $start, $end, string $id = 'in_paid'): array
    {
        $amount = $coach->is_bodybuilding_coach ? 800 : 600;
        $priceId = $coach->is_bodybuilding_coach ? 'price_bodybuilding' : 'price_powerlifting';

        return ['id' => $id, 'status' => 'paid', 'amount_paid' => $amount, 'currency' => 'gbp', 'customer' => 'cus_'.$coach->id,
            'parent' => ['subscription_details' => ['subscription' => 'sub_'.$coach->id]],
            'status_transitions' => ['paid_at' => $start->timestamp],
            'lines' => ['data' => [['amount' => $amount, 'quantity' => 1, 'pricing' => ['price_details' => ['price' => $priceId]],
                'period' => ['start' => $start->timestamp, 'end' => $end->timestamp]]]]];
    }

    private function signedWebhook(array $event)
    {
        $body = json_encode($event, JSON_THROW_ON_ERROR);
        $time = time();
        $signature = 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$body, 'whsec_test');

        return $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $body);
    }
}
