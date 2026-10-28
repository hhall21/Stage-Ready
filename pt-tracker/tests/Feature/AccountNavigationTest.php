<?php

namespace Tests\Feature;

use App\Models\CoachMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        Notification::fake();
    }

    public function test_new_coach_registration_offers_verification_and_cannot_open_a_workspace(): void
    {
        $this->postJson('/api/register', ['name' => 'New Coach', 'email' => 'new-coach@example.com',
            'password' => 'NewCoachPassword123', 'password_confirmation' => 'NewCoachPassword123', 'is_bodybuilding_coach' => true])->assertCreated();
        foreach (['/', '/register', '/payment', '/email/verify', '/subscription/cancel'] as $path) {
            $this->withoutVite()->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('auth.navigation.href', '/email/verify')->where('auth.navigation.label', 'Verify your email')
                ->where('auth.navigation.workspace_url', null));
        }
        $this->get('/dashboard')->assertRedirect('/email/verify');
        $this->getJson('/api/clients')->assertStatus(402);
        $this->getJson('/api/billing')->assertOk()->assertJsonPath('data.has_access', false);
        $this->assertDatabaseCount('coach_payments', 0);
    }

    public function test_login_returns_billing_for_a_verified_unpaid_coach_and_ignores_browser_access_claims(): void
    {
        $coach = User::factory()->create(['role' => 'coach', 'billing_exempt' => false]);
        CoachMembership::create(['user_id' => $coach->id, 'status' => 'active']);
        $this->postJson('/api/login', ['email' => $coach->email, 'password' => 'password',
            'navigation' => ['href' => '/dashboard', 'workspace_url' => '/dashboard'], 'has_access' => true])
            ->assertOk()->assertJsonPath('navigation.href', '/payment')->assertJsonPath('navigation.workspace_url', null);
        $this->withoutVite()->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auth.navigation.href', '/payment')->where('auth.navigation.label', 'Continue to billing')
            ->where('auth.navigation.workspace_url', null));
        $this->get('/dashboard')->assertRedirect('/payment');
        $this->getJson('/api/clients')->assertStatus(402);
        $this->assertDatabaseCount('coach_payments', 0);
    }

    public function test_paid_navigation_and_dashboard_access_expire_together_for_an_existing_session(): void
    {
        $coach = User::factory()->create(['role' => 'coach', 'billing_exempt' => false]);
        CoachMembership::create(['user_id' => $coach->id, 'status' => 'active', 'paid_through' => now()->addHour()]);
        $this->actingAs($coach)->withoutVite()->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auth.navigation.href', '/dashboard')->where('auth.navigation.workspace_url', '/dashboard'));
        $this->get('/dashboard')->assertOk();
        $this->travel(2)->hours();
        $this->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auth.navigation.href', '/payment')->where('auth.navigation.workspace_url', null));
        $this->get('/dashboard')->assertRedirect('/payment');
        $this->getJson('/api/clients')->assertStatus(402);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('navigation.href', '/payment');
    }

    public function test_owner_administrator_access_stays_independent_and_is_labelled_as_admin_access(): void
    {
        $owner = User::factory()->create(['role' => 'coach', 'is_admin' => true, 'billing_exempt' => false]);
        $this->actingAs($owner)->withoutVite()->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auth.navigation.href', '/admin')->where('auth.navigation.label', 'Open admin portal')
            ->where('auth.navigation.workspace_url', '/admin'));
        $this->get('/admin')->assertOk();
        $this->assertDatabaseCount('coach_payments', 0);
    }

    public function test_clients_keep_their_dashboard_for_handover_access_without_a_coach_subscription(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $this->actingAs($client)->withoutVite()->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auth.navigation.href', '/dashboard')->where('auth.navigation.workspace_url', '/dashboard'));
        $this->get('/dashboard')->assertOk();
        $this->getJson('/api/coaching-handovers')->assertOk();
    }

    public function test_guests_are_offered_sign_in_instead_of_a_workspace(): void
    {
        $this->withoutVite()->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('auth.navigation.href', '/login')->where('auth.navigation.label', 'Member sign in')
            ->where('auth.navigation.workspace_url', null));
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
