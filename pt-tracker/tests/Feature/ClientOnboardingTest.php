<?php

namespace Tests\Feature;

use App\Models\ClientInvitation;
use App\Models\TrainerClient;
use App\Models\User;
use App\Notifications\ClientInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.url' => 'http://localhost']);
        Notification::fake();
    }

    public function test_only_coaches_can_invite_and_invitations_do_not_create_accounts_early(): void
    {
        $payload = ['name' => 'New Client', 'email' => 'NEW@example.com', 'role' => 'coach'];
        $this->postJson('/api/client-invitations', $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/api/client-invitations', $payload)->assertForbidden();
        $coach = User::factory()->create(['role' => 'coach']);
        $response = $this->actingAs($coach)->postJson('/api/client-invitations', $payload)->assertCreated();
        $token = basename($response->json('accept_url'));
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertDatabaseHas('client_invitations', ['email' => 'new@example.com', 'token_hash' => hash('sha256', $token)]);
        $this->getJson('/api/client-invitations')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.token_hash');
        Notification::assertSentOnDemand(ClientInvitationNotification::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'new@example.com' && str_ends_with($notification->acceptUrl, $token));
    }

    public function test_accepting_an_invitation_creates_a_client_with_immediate_open_ended_access(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $token = $this->invite($coach);
        $this->withoutVite()->get('/join/'.$token)->assertOk()->assertInertia(fn (Assert $page) => $page->component('JoinCoach')->where('invitation.coach', $coach->name)->where('existingAccount', false));
        $password = 'MyStrongPassword123';
        $this->postJson('/api/invitations/'.$token.'/accept', ['password' => $password, 'password_confirmation' => $password, 'role' => 'coach', 'email' => 'attacker@example.com'])->assertOk();
        $client = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame('client', $client->role);
        $this->assertTrue(Hash::check($password, $client->password));
        $this->assertNotNull($client->email_verified_at);
        $this->assertDatabaseHas('trainer_clients', ['trainer_id' => $coach->id, 'client_id' => $client->id, 'end_date' => null, 'status' => 'active']);
        $this->assertSame(today()->toDateString(), TrainerClient::firstOrFail()->start_date->toDateString());
        $this->assertAuthenticatedAs($client);
        $this->getJson('/api/clients/'.$client->id.'/workouts')->assertOk();
        $this->postJson('/api/invitations/'.$token.'/accept')->assertGone();
    }

    public function test_existing_clients_must_sign_in_and_keep_their_password_and_history(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        $token = $this->invite($coach, $client->email);
        $workout = $client->workouts()->create(['workout_date' => today(), 'workout_name' => 'Existing session']);
        $hash = $client->password;
        $this->postJson('/api/invitations/'.$token.'/accept')->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson('/api/invitations/'.$token.'/accept')->assertForbidden();
        $this->actingAs($client)->postJson('/api/invitations/'.$token.'/accept', ['password' => 'ChangedWithoutConsent123'])->assertOk();
        $this->assertSame($hash, $client->fresh()->password);
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'client_id' => $client->id]);
        $this->assertDatabaseHas('trainer_clients', ['trainer_id' => $coach->id, 'client_id' => $client->id, 'status' => 'active']);
    }

    public function test_expired_cancelled_and_inactive_coach_invitations_cannot_be_used(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $token = $this->invite($coach);
        $invite = ClientInvitation::firstOrFail();
        $invite->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/api/invitations/'.$token.'/accept')->assertGone();
        $invite->update(['expires_at' => now()->addDay(), 'revoked_at' => now()]);
        $this->postJson('/api/invitations/'.$token.'/accept')->assertGone();
        $invite->update(['revoked_at' => null]);
        $coach->forceFill(['is_active' => false])->save();
        $this->postJson('/api/invitations/'.$token.'/accept')->assertGone();
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_resending_invalidates_the_old_link_and_cancelling_revokes_the_new_one(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $oldToken = $this->invite($coach);
        $invite = ClientInvitation::firstOrFail();
        $response = $this->actingAs($coach)->postJson('/api/client-invitations/'.$invite->id.'/resend')->assertOk();
        $newToken = basename($response->json('accept_url'));
        $this->assertNotSame($oldToken, $newToken);
        $this->postJson('/api/invitations/'.$oldToken.'/accept')->assertGone();
        $this->deleteJson('/api/client-invitations/'.$invite->id)->assertNoContent();
        $this->postJson('/api/invitations/'.$newToken.'/accept')->assertGone();
    }

    public function test_coaches_cannot_read_resend_or_cancel_someone_elses_invitations(): void
    {
        $owner = User::factory()->create(['role' => 'coach']);
        $this->invite($owner);
        $invite = ClientInvitation::firstOrFail();
        $this->actingAs(User::factory()->create(['role' => 'coach']))->getJson('/api/client-invitations')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/client-invitations/'.$invite->id.'/resend')->assertNotFound();
        $this->deleteJson('/api/client-invitations/'.$invite->id)->assertNotFound();
    }

    public function test_ending_coaching_removes_coach_access_and_preserves_client_records(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        TrainerClient::create(['trainer_id' => $coach->id, 'client_id' => $client->id, 'start_date' => today()]);
        $workout = $client->workouts()->create(['workout_date' => today(), 'workout_name' => 'Logged training', 'completed' => true]);
        $check = $client->dailyCheckIns()->create(['check_in_date' => today(), 'bodyweight' => 80]);
        $this->actingAs($coach)->deleteJson('/api/clients/'.$client->id.'/coaching')->assertOk();
        $this->getJson('/api/clients')->assertJsonCount(0, 'data');
        $this->getJson('/api/clients/'.$client->id.'/workouts')->assertForbidden();
        $this->getJson('/api/clients/'.$client->id.'/exercise-progress')->assertForbidden();
        $this->assertDatabaseHas('workouts', ['id' => $workout->id]);
        $this->assertDatabaseHas('daily_check_ins', ['id' => $check->id]);
        $this->postJson('/api/logout')->assertOk();
        $this->actingAs($client)->getJson('/api/clients/'.$client->id.'/workouts')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson('/api/clients/'.$client->id.'/coaching')->assertForbidden();
    }

    public function test_inviting_the_same_email_replaces_the_invitation_without_duplicates(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $oldToken = $this->invite($coach);
        $response = $this->actingAs($coach)->postJson('/api/client-invitations', ['name' => 'New Client', 'email' => 'NEW@example.com'])->assertCreated();
        $this->assertDatabaseCount('client_invitations', 1);
        $this->assertNotSame($oldToken, basename($response->json('accept_url')));
    }

    private function invite(User $coach, string $email = 'new@example.com'): string
    {
        $token = Str::random(64);
        ClientInvitation::create(['trainer_id' => $coach->id, 'name' => 'New Client', 'email' => $email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);

        return $token;
    }
}
