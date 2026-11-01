<?php

namespace Tests\Feature;

use App\Models\ClientInvitation;
use App\Models\TrainerClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CoachCapacityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        Notification::fake();
    }

    public static function disciplines(): array
    {
        return ['bodybuilding' => [true], 'powerlifting' => [false]];
    }

    #[DataProvider('disciplines')]
    public function test_the_twentieth_place_can_be_reserved_and_accepted_but_not_exceeded(bool $bodybuilding): void
    {
        $coach = User::factory()->create(['role' => 'coach', 'is_bodybuilding_coach' => $bodybuilding]);
        $this->clients($coach, 19);
        $invitation = $this->actingAs($coach)->postJson('/api/client-invitations', ['name' => 'Last client', 'email' => 'last@example.com'])->assertCreated();
        $this->getJson('/api/billing')->assertOk()->assertJsonPath('data.client_limit', 20);
        $this->getJson('/api/client-invitations')->assertOk()->assertJsonPath('capacity.active_clients', 19)
            ->assertJsonPath('capacity.pending_invitations', 1)->assertJsonPath('capacity.remaining', 0);
        $this->postJson('/api/client-invitations', ['name' => 'Over limit', 'email' => 'over@example.com'])->assertUnprocessable();
        $resend = $this->postJson('/api/client-invitations/'.$invitation->json('data.id').'/resend')->assertOk();
        $this->postJson('/api/logout')->assertOk();
        $token = basename($resend->json('accept_url'));
        $this->postJson('/api/invitations/'.$token.'/accept', ['password' => 'LastClientPassword123', 'password_confirmation' => 'LastClientPassword123'])->assertOk();
        $this->assertSame(20, $coach->clients()->wherePivot('status', 'active')->count());
        $this->postJson('/api/logout')->assertOk();
        $this->actingAs($coach)->postJson('/api/client-invitations', ['name' => 'Still over', 'email' => 'over@example.com'])->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['email' => 'over@example.com']);
    }

    public function test_full_capacity_blocks_an_older_invitation_without_creating_an_account_or_consuming_the_link(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $this->clients($coach, 20);
        [$invite, $token] = $this->invitation($coach, 'waiting@example.com');
        $this->postJson('/api/invitations/'.$token.'/accept', ['password' => 'WaitingClientPassword123', 'password_confirmation' => 'WaitingClientPassword123'])
            ->assertUnprocessable()->assertJsonValidationErrors('invitation');
        $this->assertDatabaseMissing('users', ['email' => 'waiting@example.com']);
        $this->assertNull($invite->fresh()->accepted_at);
        $this->assertSame(20, TrainerClient::where('trainer_id', $coach->id)->count());
        Notification::assertNothingSent();
    }

    public function test_pending_places_are_freed_by_cancellation_or_expiry_and_are_scoped_to_each_coach(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        for ($index = 0; $index < 20; $index++) {
            [$invite] = $this->invitation($coach, 'pending'.$index.'@example.com');
        }
        $this->actingAs($coach)->postJson('/api/client-invitations', ['name' => 'Full', 'email' => 'full@example.com'])->assertUnprocessable();
        $this->deleteJson('/api/client-invitations/'.$invite->id)->assertNoContent();
        $this->postJson('/api/client-invitations', ['name' => 'Replacement', 'email' => 'replacement@example.com'])->assertCreated();
        ClientInvitation::where('trainer_id', $coach->id)->where('email', 'pending0@example.com')->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/api/client-invitations', ['name' => 'After expiry', 'email' => 'after-expiry@example.com'])->assertCreated();
        $other = User::factory()->create(['role' => 'coach']);
        $this->actingAs($other)->postJson('/api/client-invitations', ['name' => 'Other coach client', 'email' => 'other@example.com'])->assertCreated();
    }

    public function test_deactivating_an_account_does_not_bypass_capacity_and_ending_coaching_frees_a_place(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $this->clients($coach, 20);
        $client = $coach->clients()->firstOrFail();
        $client->forceFill(['is_active' => false])->save();
        $this->actingAs($coach)->postJson('/api/client-invitations', ['name' => 'Full', 'email' => 'full@example.com'])->assertUnprocessable();
        $client->workouts()->create(['workout_name' => 'Preserved history', 'workout_date' => today()->subDay()]);
        TrainerClient::where('trainer_id', $coach->id)->where('client_id', $client->id)->update(['status' => 'inactive', 'end_date' => today()]);
        $this->postJson('/api/client-invitations', ['name' => 'Free place', 'email' => 'free@example.com'])->assertCreated();
        $this->assertDatabaseHas('workouts', ['client_id' => $client->id, 'workout_name' => 'Preserved history']);
        $this->assertDatabaseHas('users', ['id' => $client->id]);
    }

    public function test_an_expired_invitation_cannot_be_renewed_when_other_places_are_full(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $this->clients($coach, 20);
        [$invite] = $this->invitation($coach, 'expired@example.com');
        $invite->update(['expires_at' => now()->subDay()]);
        $hash = $invite->token_hash;
        $this->actingAs($coach)->postJson('/api/client-invitations/'.$invite->id.'/resend')->assertUnprocessable();
        $this->assertSame($hash, $invite->fresh()->token_hash);
        $this->assertTrue($invite->fresh()->expires_at->isPast());
    }

    private function clients(User $coach, int $count): void
    {
        $clients = User::factory()->count($count)->create(['role' => 'client']);
        $coach->clients()->attach($clients->modelKeys(), ['status' => 'active', 'start_date' => today()->toDateString()]);
    }

    private function invitation(User $coach, string $email): array
    {
        $token = Str::random(64);

        return [ClientInvitation::create(['trainer_id' => $coach->id, 'name' => 'Pending client', 'email' => $email,
            'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]), $token];
    }
}
