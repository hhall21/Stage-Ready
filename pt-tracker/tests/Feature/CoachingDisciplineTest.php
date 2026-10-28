<?php

namespace Tests\Feature;

use App\Models\DailyCheckIn;
use App\Models\ProgressPhoto;
use App\Models\TrainerClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoachingDisciplineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(12, 0));
        Storage::fake('checkin_photos');
    }

    public function test_coach_discipline_is_read_only_and_has_no_self_service_update_endpoint(): void
    {
        [$coach, $client] = $this->pair();
        $url = '/api/user/coaching-type';
        $this->assertTrue($coach->fresh()->is_bodybuilding_coach);
        $this->patchJson($url, ['is_bodybuilding_coach' => false])->assertNotFound();
        $this->actingAs($client)->patchJson($url, ['is_bodybuilding_coach' => true])->assertNotFound();
        $this->actingAs($coach)->getJson('/api/user')->assertJsonPath('data.is_bodybuilding_coach', true);
        $this->patchJson($url, ['is_bodybuilding_coach' => false])->assertNotFound();
        $this->assertTrue($coach->fresh()->is_bodybuilding_coach);
        [$powerlifting] = $this->pair(false);
        $this->actingAs($powerlifting)->getJson('/api/user')->assertJsonPath('data.is_bodybuilding_coach', false);
        $this->patchJson($url, ['is_bodybuilding_coach' => true])->assertNotFound();
        $this->assertFalse($powerlifting->fresh()->is_bodybuilding_coach);
    }

    public function test_bodybuilding_clients_and_coaches_have_access_while_unassigned_clients_do_not(): void
    {
        [$coach, $client] = $this->pair();
        $client->forceFill(['is_bodybuilding_coach' => false])->save();
        [$checkIn, $photo] = $this->history($client, $coach);
        foreach ([$coach, $client] as $user) {
            $this->actingAs($user)->getJson('/api/clients/'.$client->id.'/daily-check-ins')
                ->assertOk()->assertJsonPath('posing_enabled', true);
            $this->getJson($this->base($client, $checkIn))->assertOk()->assertJsonCount(1, 'data.photos');
            $this->get('/api/daily-check-in-photos/'.$photo->id)->assertOk();
        }
        $unassigned = User::factory()->create(['is_bodybuilding_coach' => true]);
        $unassignedCheck = $unassigned->dailyCheckIns()->create(['check_in_date' => today()]);
        $this->actingAs($unassigned)->getJson('/api/clients/'.$unassigned->id.'/daily-check-ins')
            ->assertOk()->assertJsonPath('posing_enabled', false);
        $this->getJson($this->base($unassigned, $unassignedCheck))->assertForbidden();
    }

    public function test_powerlifting_blocks_all_photo_endpoints_and_preserves_existing_history(): void
    {
        [$coach, $client] = $this->pair(false);
        [$checkIn, $photo] = $this->history($client, $coach);
        $base = $this->base($client, $checkIn);
        $this->actingAs($coach);
        $this->getJson('/api/clients/'.$client->id.'/daily-check-ins')->assertJsonPath('posing_enabled', false);
        $this->getJson($base)->assertForbidden();
        $this->getJson('/api/daily-check-in-photos/'.$photo->id)->assertForbidden();
        $this->putJson($base.'/review', ['photo_version' => 0, 'posing_rating' => 7, 'feedback' => 'Review', 'is_competition_prep' => false])
            ->assertForbidden();
        $this->actingAs($client)->getJson('/api/clients/'.$client->id.'/daily-check-ins')->assertOk()->assertJsonPath('posing_enabled', false);
        $this->getJson($base)->assertForbidden();
        $this->getJson('/api/daily-check-in-photos/'.$photo->id)->assertForbidden();
        $this->postJson($base.'/submit', ['photo_version' => 0])->assertForbidden();
        $this->deleteJson($base.'/photos/front_relaxed', ['photo_version' => 0])->assertForbidden();
        $this->post($base.'/photos/rear_relaxed', ['photo_version' => 0, 'photo' => UploadedFile::fake()->createWithContent('pose.png', $this->image())], ['Accept' => 'application/json'])
            ->assertForbidden();
        $this->patchJson('/api/clients/'.$client->id.'/daily-check-ins/'.$checkIn->id, ['bodyweight' => 90, 'notes' => 'Normal daily check-in'])
            ->assertOk()->assertJsonPath('data.bodyweight', '90.00');
        $this->putJson('/api/clients/'.$client->id.'/end-of-day-nutrition', ['check_in_date' => today()->toDateString(), 'hunger_level' => 3])
            ->assertOk()->assertJsonPath('data.hunger_level', 3);
        $this->assertDatabaseCount('progress_photos', 1);
        $this->assertDatabaseCount('check_in_posing_reviews', 1);
        $this->assertSame(0, $checkIn->fresh()->photo_version);
        Storage::disk('checkin_photos')->assertExists($photo->file_path);
        $this->actingAs($coach)->patchJson('/api/user/coaching-type', ['is_bodybuilding_coach' => true])->assertNotFound();
        $this->actingAs($client)->getJson($base)->assertForbidden();
    }

    public function test_a_powerlifting_coach_cannot_see_photos_when_the_client_also_has_a_bodybuilding_coach(): void
    {
        [$bodybuilding, $client] = $this->pair();
        $powerlifting = User::factory()->create(['role' => 'coach', 'is_bodybuilding_coach' => false]);
        TrainerClient::create(['trainer_id' => $powerlifting->id, 'client_id' => $client->id, 'start_date' => today()]);
        [$checkIn, $photo] = $this->history($client, $bodybuilding);
        $this->actingAs($powerlifting)->getJson('/api/clients/'.$client->id.'/daily-check-ins')->assertOk()->assertJsonPath('posing_enabled', false);
        $this->getJson($this->base($client, $checkIn))->assertForbidden();
        $this->getJson('/api/daily-check-in-photos/'.$photo->id)->assertForbidden();
        $this->actingAs($client)->getJson('/api/clients/'.$client->id.'/daily-check-ins')->assertJsonPath('posing_enabled', true);
        $this->actingAs($bodybuilding)->deleteJson('/api/clients/'.$client->id.'/coaching')->assertOk();
        $this->actingAs($client)->getJson('/api/clients/'.$client->id.'/daily-check-ins')->assertJsonPath('posing_enabled', false);
        $this->getJson($this->base($client, $checkIn))->assertForbidden();
        $this->assertDatabaseCount('progress_photos', 1);
        $this->assertDatabaseCount('check_in_posing_reviews', 1);
    }

    public function test_only_current_active_bodybuilding_assignments_enable_client_photo_tools(): void
    {
        [$coach, $client] = $this->pair();
        [$checkIn] = $this->history($client, $coach);
        $assignment = TrainerClient::firstOrFail();
        $this->actingAs($client);
        foreach ([['status' => 'inactive'], ['start_date' => '2026-10-27'], ['end_date' => '2026-10-25']] as $change) {
            $assignment->forceFill(['status' => 'active', 'start_date' => '2026-10-01', 'end_date' => null, ...$change])->save();
            $this->getJson('/api/clients/'.$client->id.'/daily-check-ins')->assertOk()->assertJsonPath('posing_enabled', false);
            $this->getJson($this->base($client, $checkIn))->assertForbidden();
        }
        $assignment->forceFill(['start_date' => '2026-10-01', 'end_date' => '2026-10-26'])->save();
        $this->getJson('/api/clients/'.$client->id.'/daily-check-ins')->assertJsonPath('posing_enabled', true);
        $coach->forceFill(['is_active' => false])->save();
        $this->getJson('/api/clients/'.$client->id.'/daily-check-ins')->assertJsonPath('posing_enabled', false);
        $coach->forceFill(['is_active' => true, 'role' => 'client'])->save();
        $this->getJson('/api/clients/'.$client->id.'/daily-check-ins')->assertJsonPath('posing_enabled', false);
    }

    /** @return array{User, User} */
    private function pair(bool $bodybuilding = true): array
    {
        $coach = User::factory()->create(['role' => 'coach', 'is_bodybuilding_coach' => $bodybuilding]);
        $client = User::factory()->create();
        TrainerClient::create(['trainer_id' => $coach->id, 'client_id' => $client->id, 'start_date' => '2026-10-01']);

        return [$coach, $client];
    }

    /** @return array{DailyCheckIn, ProgressPhoto} */
    private function history(User $client, User $coach): array
    {
        $checkIn = $client->dailyCheckIns()->create(['check_in_date' => today()]);
        $checkIn->forceFill(['submitted_at' => now()])->save();
        $path = $client->id.'/'.$checkIn->id.'/pose.png';
        Storage::disk('checkin_photos')->put($path, $this->image());
        $photo = $checkIn->posePhotos()->create(['photo_type' => 'front', 'pose_key' => 'front_relaxed', 'file_path' => $path, 'mime_type' => 'image/png',
            'coach_viewed_at' => now(), 'retained_by_coach_id' => $coach->id]);
        $checkIn->posingReviews()->create(['coach_id' => $coach->id, 'photo_version' => 0, 'posing_rating' => 7, 'feedback' => 'Existing feedback', 'reviewed_at' => now()]);

        return [$checkIn, $photo];
    }

    private function base(User $client, DailyCheckIn $checkIn): string
    {
        return '/api/clients/'.$client->id.'/daily-check-ins/'.$checkIn->id.'/posing';
    }

    private function image(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aH1kAAAAASUVORK5CYII=');
    }
}
