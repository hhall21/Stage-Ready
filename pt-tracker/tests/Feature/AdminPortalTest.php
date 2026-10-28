<?php

namespace Tests\Feature;

use App\Models\ClientInvitation;
use App\Models\User;
use App\Notifications\CoachWelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class AdminPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.url' => 'http://localhost']);
        Notification::fake();
    }

    public function test_portal_and_every_admin_endpoint_require_an_active_administrator(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $this->get('/admin')->assertRedirect('/login');
        $this->getJson('/api/admin/users')->assertUnauthorized();
        foreach ([$coach, User::factory()->create()] as $user) {
            $this->actingAs($user)->get('/admin')->assertForbidden();
            $this->getJson('/api/admin/users')->assertForbidden();
            $this->postJson('/api/admin/coaches', $this->coachData())->assertForbidden();
            $this->patchJson('/api/admin/users/'.$coach->id.'/access', ['is_active' => false])->assertForbidden();
            $this->postJson('/api/admin/coaches/'.$coach->id.'/setup-link')->assertForbidden();
        }
        $admin = $this->admin();
        $this->actingAs($admin)->withoutVite()->get('/admin')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin')->where('auth.user.is_admin', true));
        $admin->forceFill(['is_active' => false])->save();
        $this->getJson('/api/admin/users')->assertForbidden();
        $this->assertGuest();
    }

    public function test_directory_is_paginated_searchable_and_does_not_return_passwords_or_private_records(): void
    {
        $admin = $this->admin();
        $coach = User::factory()->create(['role' => 'coach', 'name' => 'Searchable Coach']);
        $client = User::factory()->create(['name' => 'Linked client']);
        $coach->clients()->attach($client, ['status' => 'active', 'start_date' => today()->toDateString()]);
        $futureClient = User::factory()->create();
        $coach->clients()->attach($futureClient, ['status' => 'active', 'start_date' => today()->addDay()->toDateString()]);
        $this->actingAs($admin)->getJson('/api/admin/users?search=Searchable&role=coach&status=active')
            ->assertOk()->assertJsonCount(1, 'users.data')
            ->assertJsonPath('users.data.0.id', $coach->id)
            ->assertJsonPath('users.data.0.active_clients_count', 1)
            ->assertJsonMissingPath('users.data.0.password')
            ->assertJsonMissingPath('users.data.0.remember_token')
            ->assertJsonMissingPath('users.data.0.daily_check_ins')
            ->assertJsonMissingPath('users.data.0.setup_url');
        $this->getJson('/api/admin/users?role=admin')->assertJsonCount(1, 'users.data')->assertJsonPath('users.data.0.id', $admin->id);
        User::factory()->count(22)->create();
        $this->getJson('/api/admin/users')->assertJsonCount(20, 'users.data')->assertJsonPath('users.last_page', 2);
        $this->getJson('/api/admin/users?role=invalid')->assertUnprocessable();
    }

    public function test_overview_excludes_expired_revoked_accepted_and_inactive_coach_invitations(): void
    {
        $admin = $this->admin();
        $coach = User::factory()->create(['role' => 'coach']);
        $inactive = User::factory()->create(['role' => 'coach', 'is_active' => false]);
        User::factory()->create();
        foreach ([[], ['expires_at' => now()->subMinute()], ['revoked_at' => now()], ['accepted_at' => now()], ['trainer_id' => $inactive->id]] as $overrides) {
            ClientInvitation::create([...['trainer_id' => $coach->id, 'name' => 'Invited client', 'email' => Str::random(12).'@example.com',
                'token_hash' => hash('sha256', Str::random(64)), 'expires_at' => now()->addDays(7)], ...$overrides]);
        }
        $this->actingAs($admin)->getJson('/api/admin/users')->assertOk()
            ->assertJsonPath('summary.active_coaches', 2)->assertJsonPath('summary.active_clients', 1)
            ->assertJsonPath('summary.inactive_accounts', 1)->assertJsonPath('summary.pending_invitations', 1);
    }

    public function test_coach_creation_sends_a_password_setup_link_without_granting_admin_access(): void
    {
        $response = $this->actingAs($this->admin())->postJson('/api/admin/coaches', [
            ...$this->coachData(), 'email' => 'NEW.COACH@example.com', 'is_bodybuilding_coach' => false,
            'role' => 'client', 'is_admin' => true, 'password' => 'AttackerPassword123', 'is_active' => false,
        ])->assertCreated()->assertJsonPath('delivery', 'local')->assertJsonPath('expires_in_minutes', 60);
        $coach = User::where('email', 'new.coach@example.com')->firstOrFail();
        $this->assertSame('coach', $coach->role);
        $this->assertFalse($coach->is_admin);
        $this->assertTrue($coach->is_active);
        $this->assertFalse($coach->is_bodybuilding_coach);
        $this->assertFalse(Hash::check('AttackerPassword123', $coach->password));
        $response->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');
        Notification::assertSentTo($coach, CoachWelcomeNotification::class, fn ($notification) => $notification->setupUrl === $response->json('setup_url'));
        $token = basename(parse_url($response->json('setup_url'), PHP_URL_PATH));
        $this->assertTrue(Password::broker()->tokenExists($coach, $token));
        $this->postJson('/api/logout')->assertOk();
        $this->postJson('/api/reset-password', ['token' => $token, 'email' => $coach->email,
            'password' => 'CoachPassword123', 'password_confirmation' => 'CoachPassword123'])->assertOk();
        $this->postJson('/api/login', ['email' => $coach->email, 'password' => 'CoachPassword123'])->assertOk()
            ->assertJsonPath('data.is_admin', false);
        $this->postJson('/api/client-invitations', ['name' => 'First client', 'email' => 'first.client@example.com'])->assertStatus(402);
        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_coach_creation_validates_email_uniqueness_and_discipline(): void
    {
        $this->actingAs($this->admin());
        $this->postJson('/api/admin/coaches', $this->coachData())->assertCreated();
        $this->postJson('/api/admin/coaches', [...$this->coachData(), 'email' => 'NEW.COACH@EXAMPLE.COM'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/admin/coaches', ['name' => '', 'email' => 'invalid', 'is_bodybuilding_coach' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'is_bodybuilding_coach']);
    }

    public function test_access_changes_preserve_history_assignments_role_and_discipline(): void
    {
        $admin = $this->admin();
        $coach = User::factory()->create(['role' => 'coach', 'is_bodybuilding_coach' => false]);
        $client = User::factory()->create();
        $coach->clients()->attach($client, ['status' => 'active', 'start_date' => today()->toDateString()]);
        $workout = $client->workouts()->create(['workout_date' => today(), 'workout_name' => 'Logged history', 'completed' => true]);
        $this->actingAs($admin)->patchJson('/api/admin/users/'.$coach->id.'/access', [
            'is_active' => false, 'is_admin' => true, 'role' => 'client', 'is_bodybuilding_coach' => true,
        ])->assertOk();
        $coach->refresh();
        $this->assertFalse($coach->is_active);
        $this->assertFalse($coach->is_admin);
        $this->assertSame('coach', $coach->role);
        $this->assertFalse($coach->is_bodybuilding_coach);
        $this->assertFalse($client->hasActiveCoach());
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'completed' => true]);
        $this->assertDatabaseHas('trainer_clients', ['trainer_id' => $coach->id, 'client_id' => $client->id, 'status' => 'active', 'end_date' => null]);
        $this->patchJson('/api/admin/users/'.$coach->id.'/access', ['is_active' => true])->assertOk();
        $this->assertTrue($client->hasActiveCoach());
        $this->patchJson('/api/admin/users/'.$client->id.'/access', ['is_active' => false])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $client->id, 'is_active' => false]);
        $this->assertDatabaseHas('workouts', ['id' => $workout->id]);
        $this->actingAs($client->fresh())->getJson('/api/user')->assertForbidden();
        $this->assertGuest();
    }

    public function test_administrator_accounts_cannot_be_deactivated_and_access_input_is_validated(): void
    {
        $admin = $this->admin();
        $other = $this->admin();
        $coach = User::factory()->create(['role' => 'coach']);
        $this->actingAs($admin);
        foreach ([$admin, $other] as $protected) {
            $this->patchJson('/api/admin/users/'.$protected->id.'/access', ['is_active' => false])->assertUnprocessable();
            $this->assertTrue($protected->fresh()->is_active);
        }
        $this->patchJson('/api/admin/users/'.$coach->id.'/access', ['is_active' => 'invalid'])->assertUnprocessable();
        $this->patchJson('/api/admin/users/999999/access', ['is_active' => false])->assertNotFound();
    }

    public function test_fresh_setup_links_invalidate_old_tokens_and_cannot_target_clients_inactive_coaches_or_admins(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $first = $this->postJson('/api/admin/coaches', $this->coachData())->assertCreated();
        $coach = User::where('email', 'new.coach@example.com')->firstOrFail();
        $oldToken = basename(parse_url($first->json('setup_url'), PHP_URL_PATH));
        $fresh = $this->postJson('/api/admin/coaches/'.$coach->id.'/setup-link')->assertOk();
        $newToken = basename(parse_url($fresh->json('setup_url'), PHP_URL_PATH));
        $this->assertFalse(Password::broker()->tokenExists($coach, $oldToken));
        $this->assertTrue(Password::broker()->tokenExists($coach, $newToken));
        Notification::assertSentToTimes($coach, CoachWelcomeNotification::class, 2);
        $coach->forceFill(['is_active' => false])->save();
        $this->postJson('/api/admin/coaches/'.$coach->id.'/setup-link')->assertUnprocessable();
        $client = User::factory()->create();
        $this->postJson('/api/admin/coaches/'.$client->id.'/setup-link')->assertNotFound();
        $this->postJson('/api/admin/coaches/'.$admin->id.'/setup-link')->assertNotFound();
    }

    public function test_mail_failure_keeps_the_coach_account_and_provides_a_manual_setup_link(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Mail unavailable'));
        $this->actingAs($this->admin())->postJson('/api/admin/coaches', $this->coachData())
            ->assertCreated()->assertJsonPath('delivery', 'failed')->assertJsonStructure(['setup_url']);
        $this->assertDatabaseHas('users', ['email' => 'new.coach@example.com', 'role' => 'coach', 'is_admin' => false]);
    }

    public function test_admin_flag_is_guarded_against_mass_assignment(): void
    {
        $user = new User;
        $user->fill(['name' => 'Ordinary account', 'email' => 'ordinary@example.com', 'password' => 'Password12345', 'is_admin' => true]);
        $this->assertFalse($user->is_admin);
    }

    public function test_administrator_flag_does_not_grant_access_to_an_unassigned_clients_private_records(): void
    {
        $client = User::factory()->create();
        $this->actingAs($this->admin())->getJson('/api/clients/'.$client->id.'/daily-check-ins')->assertForbidden();
        $this->getJson('/api/clients/'.$client->id.'/workouts')->assertForbidden();
    }

    public function test_admin_account_writes_require_csrf_protection(): void
    {
        $this->actingAs($this->admin());
        $this->app['env'] = 'local';
        $this->withSession(['_token' => 'admin-csrf-token'])
            ->postJson('/api/admin/coaches', $this->coachData())->assertStatus(419);
        $this->withHeader('X-CSRF-TOKEN', 'admin-csrf-token')
            ->postJson('/api/admin/coaches', $this->coachData())->assertCreated();
    }

    public function test_bootstrap_command_creates_an_owner_without_a_shared_password_or_email(): void
    {
        $this->assertSame(0, Artisan::call('stage-ready:make-admin', ['email' => 'OWNER@example.com', '--name' => 'Owner Admin']));
        $admin = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertTrue($admin->is_active);
        $this->assertSame('coach', $admin->role);
        $this->assertFalse(Hash::check('password', $admin->password));
        $output = Artisan::output();
        preg_match('~/reset-password/([a-zA-Z0-9]+)\?email=~', $output, $matches);
        $this->assertTrue(Password::broker()->tokenExists($admin, $matches[1]));
        $this->assertSame(0, Artisan::call('stage-ready:make-admin', ['email' => 'owner@example.com']));
        $this->assertSame($admin->password, $admin->fresh()->password);
        $this->assertDatabaseCount('users', 1);
        Notification::assertNothingSent();
    }

    public function test_bootstrap_command_can_promote_an_existing_coach_but_rejects_invalid_or_inactive_accounts(): void
    {
        $coach = User::factory()->create(['role' => 'coach', 'is_bodybuilding_coach' => false]);
        $hash = $coach->password;
        $this->assertSame(0, Artisan::call('stage-ready:make-admin', ['email' => $coach->email]));
        $this->assertTrue($coach->fresh()->is_admin);
        $this->assertFalse($coach->fresh()->is_bodybuilding_coach);
        $this->assertSame($hash, $coach->fresh()->password);
        foreach ([User::factory()->create(), User::factory()->create(['role' => 'coach', 'is_active' => false])] as $user) {
            $this->assertSame(1, Artisan::call('stage-ready:make-admin', ['email' => $user->email]));
            $this->assertFalse($user->fresh()->is_admin);
        }
        $this->assertSame(1, Artisan::call('stage-ready:make-admin', ['email' => 'invalid']));
        $this->assertSame(1, Artisan::call('stage-ready:make-admin', ['email' => 'missing.name@example.com']));
        $this->assertDatabaseMissing('users', ['email' => 'missing.name@example.com']);
    }

    /** @return array{name: string, email: string, is_bodybuilding_coach: bool} */
    private function coachData(): array
    {
        return ['name' => 'New Coach', 'email' => 'new.coach@example.com', 'is_bodybuilding_coach' => true];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'coach', 'is_admin' => true]);
    }
}
