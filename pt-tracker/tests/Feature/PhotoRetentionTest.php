<?php

namespace Tests\Feature;

use App\Jobs\DeleteTemporaryCheckInPhotos;
use App\Models\DailyCheckIn;
use App\Models\ProgressPhoto;
use App\Models\User;
use App\Services\TemporaryPosePhotos;
use App\Support\BodybuildingPoses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class PhotoRetentionTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aBBkAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        Storage::fake('checkin_photos');
        Storage::fake('temporary_checkin_photos');
        $this->travelTo(now()->startOfSecond());
    }

    public function test_all_thirteen_upload_temporarily_and_coaches_choose_any_four_without_exceeding_the_shared_cap(): void
    {
        [$coach, $client, $check] = $this->state();
        foreach (BodybuildingPoses::keys() as $pose) {
            $this->upload($client, $check, $pose);
        }
        $this->assertDatabaseCount('progress_photos', 0);
        $this->assertCount(26, Storage::disk('temporary_checkin_photos')->allFiles());
        $this->actingAs($coach)->getJson($this->base($client, $check))->assertOk()->assertJsonCount(13, 'data.photos')
            ->assertJsonPath('data.retained_photo_limit', 4);
        foreach (['front_double_biceps', 'side_chest', 'favourite_classic_pose', 'rear_relaxed'] as $pose) {
            $this->get($this->photo($client, $check, $pose).'?download=1')->assertOk()
                ->assertHeader('Content-Type', 'image/png')
                ->assertHeader('Content-Disposition', 'attachment; filename=stage-ready-'.$pose.'-'.today()->toDateString().'.png')
                ->assertStreamedContent(base64_decode(self::PNG));
            $this->keep($coach, $client, $check, $pose)->assertOk()->assertJsonCount(13, 'data.photos');
        }
        $this->assertDatabaseCount('progress_photos', 4);
        $this->keep($coach, $client, $check, 'back_lat_spread')->assertConflict();
        $other = User::factory()->create(['role' => 'coach']);
        $other->clients()->attach($client, ['status' => 'active', 'start_date' => today()->toDateString()]);
        $this->keep($other, $client, $check, 'back_lat_spread')->assertConflict();
        $this->keep($coach, $client, $check, 'side_chest', false)->assertOk();
        $this->keep($other, $client, $check, 'back_lat_spread')->assertOk();
        $this->assertDatabaseCount('progress_photos', 4);
        $this->assertDatabaseMissing('progress_photos', ['pose_key' => 'side_chest']);
        $this->actingAs($client)->getJson('/api/clients/'.$client->id.'/daily-check-ins/'.$check->id)
            ->assertOk()->assertJsonPath('data.pose_photos_count', 13)->assertJsonMissingPath('data.retained_pose_photos');
        $this->assertSame(13, $check->fresh()->photo_version);
    }

    public function test_saved_expiry_starts_on_first_coach_view_and_cleanup_preserves_measurements_submission_and_feedback(): void
    {
        [$coach, $client, $check] = $this->state();
        $this->upload($client, $check, 'front_relaxed');
        $this->upload($client, $check, 'most_muscular');
        $this->actingAs($client)->get($this->photo($client, $check, 'front_relaxed'))->assertOk();
        $this->assertNull(app(TemporaryPosePhotos::class)->metadata($client->id, $check->id, 'front_relaxed')['coach_viewed_at']);
        $this->postJson($this->base($client, $check).'/submit', ['photo_version' => 2])->assertOk();
        $this->actingAs($coach)->putJson($this->base($client, $check).'/review', [
            'photo_version' => 2, 'posing_rating' => 8, 'feedback' => 'Keep this feedback.', 'is_competition_prep' => false,
        ])->assertOk();
        $submitted = $check->fresh()->submitted_at->toISOString();
        $this->travel(6)->days();
        $firstView = now()->toImmutable();
        $this->get($this->photo($client, $check, 'front_relaxed'))->assertOk();
        $this->travel(2)->hours();
        $this->get($this->photo($client, $check, 'front_relaxed'))->assertOk();
        $this->keep($coach, $client, $check, 'front_relaxed')->assertOk();
        $saved = ProgressPhoto::sole();
        $deadline = $firstView->addDays(7);
        $this->assertTrue($deadline->equalTo($saved->expires_at));
        $this->assertTrue($firstView->equalTo($saved->coach_viewed_at));
        $this->travel(1)->days();
        $this->artisan('photos:expire --dry-run')->assertSuccessful();
        $this->assertCount(4, Storage::disk('temporary_checkin_photos')->allFiles());
        $this->artisan('photos:expire')->assertSuccessful();
        $this->assertSame([], Storage::disk('temporary_checkin_photos')->allFiles());
        $this->assertDatabaseCount('progress_photos', 1);
        $this->getJson($this->base($client, $check))->assertJsonCount(1, 'data.photos');
        $this->keep($coach, $client, $check, 'front_relaxed')->assertOk();
        $this->assertTrue($deadline->equalTo($saved->fresh()->expires_at));
        $this->travelTo($deadline->subSecond());
        $this->get($this->photo($client, $check, 'front_relaxed'))->assertOk();
        $this->travel(1)->seconds();
        $this->get($this->photo($client, $check, 'front_relaxed').'?download=1')->assertNotFound();
        $this->getJson($this->base($client, $check))->assertJsonCount(0, 'data.photos')->assertJsonPath('data.reviews.0.is_current', true);
        $this->artisan('photos:expire')->assertSuccessful();
        $this->assertDatabaseCount('progress_photos', 0);
        $this->assertSame([], Storage::disk('checkin_photos')->allFiles());
        $this->assertSame('81.40', $check->fresh()->bodyweight);
        $this->assertSame('History stays.', $check->fresh()->notes);
        $this->assertSame($submitted, $check->fresh()->submitted_at->toISOString());
        $this->assertDatabaseHas('check_in_posing_reviews', ['check_in_id' => $check->id, 'feedback' => 'Keep this feedback.']);
    }

    public function test_removing_and_selecting_again_or_another_coach_viewing_cannot_extend_the_deadline(): void
    {
        [$coach, $client, $check] = $this->state();
        $this->upload($client, $check, 'side_chest');
        $this->keep($coach, $client, $check, 'side_chest')->assertOk();
        $deadline = ProgressPhoto::sole()->expires_at;
        $this->travel(3)->days();
        $this->keep($coach, $client, $check, 'side_chest', false)->assertOk();
        $this->assertDatabaseCount('progress_photos', 0);
        $other = User::factory()->create(['role' => 'coach']);
        $other->clients()->attach($client, ['status' => 'active', 'start_date' => today()->toDateString()]);
        $this->actingAs($other)->get($this->photo($client, $check, 'side_chest'))->assertOk();
        $this->keep($other, $client, $check, 'side_chest')->assertOk();
        $this->assertTrue($deadline->equalTo(ProgressPhoto::sole()->expires_at));
    }

    public function test_replacing_a_kept_photo_removes_the_old_copy_and_requires_a_new_selection_and_view_clock(): void
    {
        [$coach, $client, $check] = $this->state();
        $this->upload($client, $check, 'front_relaxed');
        $this->keep($coach, $client, $check, 'front_relaxed')->assertOk();
        $old = ProgressPhoto::sole();
        $this->travel(2)->days();
        $this->upload($client, $check, 'front_relaxed');
        $this->assertDatabaseCount('progress_photos', 0);
        Storage::disk('checkin_photos')->assertMissing($old->file_path);
        $this->assertNull(app(TemporaryPosePhotos::class)->metadata($client->id, $check->id, 'front_relaxed')['coach_viewed_at']);
        $this->actingAs($coach)->putJson($this->photo($client, $check, 'front_relaxed').'/retention', ['photo_version' => 1, 'retained' => true])->assertConflict();
        $this->keep($coach, $client, $check, 'front_relaxed')->assertOk();
        $this->assertTrue(now()->addDays(7)->equalTo(ProgressPhoto::sole()->expires_at));
        $this->assertSame(2, $check->fresh()->photo_version);
    }

    public function test_only_eligible_assigned_coaches_can_start_the_clock_or_keep_photos(): void
    {
        [$coach, $client, $check] = $this->state();
        $this->upload($client, $check, 'most_muscular');
        $payload = ['photo_version' => 1, 'retained' => true];
        $this->putJson($this->photo($client, $check, 'most_muscular').'/retention', $payload)->assertForbidden();
        $foreign = User::factory()->create(['role' => 'coach', 'is_admin' => true]);
        $this->keep($foreign, $client, $check, 'most_muscular')->assertForbidden();
        $this->get($this->photo($client, $check, 'most_muscular').'?download=1')->assertForbidden();
        $powerlifting = User::factory()->create(['role' => 'coach', 'is_bodybuilding_coach' => false]);
        $powerlifting->clients()->attach($client, ['status' => 'active', 'start_date' => today()->toDateString()]);
        $this->keep($powerlifting, $client, $check, 'most_muscular')->assertForbidden();
        $this->assertDatabaseCount('progress_photos', 0);
        $this->assertNull(app(TemporaryPosePhotos::class)->metadata($client->id, $check->id, 'most_muscular')['coach_viewed_at']);
        $coach->forceFill(['billing_exempt' => false])->save();
        $this->keep($coach->fresh(), $client, $check, 'most_muscular')->assertStatus(402);
    }

    public function test_a_failed_saved_photo_insert_leaves_no_database_row_or_saved_file_and_keeps_the_review_photo(): void
    {
        [$coach, $client, $check] = $this->state();
        $this->upload($client, $check, 'most_muscular');
        $dispatcher = ProgressPhoto::getEventDispatcher();
        ProgressPhoto::setEventDispatcher(clone $dispatcher);
        ProgressPhoto::creating(function (): void {
            throw new RuntimeException('Simulated retained photo failure.');
        });
        try {
            $this->keep($coach, $client, $check, 'most_muscular')->assertServerError();
        } finally {
            ProgressPhoto::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('progress_photos', 0);
        $this->assertSame([], Storage::disk('checkin_photos')->allFiles());
        $this->assertCount(2, Storage::disk('temporary_checkin_photos')->allFiles());
        $this->assertSame(1, $check->fresh()->photo_version);
        $this->keep($coach, $client, $check, 'most_muscular')->assertOk();
    }

    public function test_legacy_photos_move_to_temporary_storage_without_extending_their_original_deadline(): void
    {
        [$coach, $client, $check] = $this->state();
        $path = $client->id.'/'.$check->id.'/legacy.png';
        Storage::disk('checkin_photos')->put($path, base64_decode(self::PNG));
        $deadline = now()->addDays(2)->toImmutable();
        $photo = $check->posePhotos()->create(['pose_key' => 'side_chest', 'photo_type' => 'side', 'file_path' => $path,
            'mime_type' => 'image/png', 'expires_at' => $deadline]);
        $this->artisan('photos:expire --dry-run')->assertSuccessful();
        $this->assertDatabaseHas('progress_photos', ['id' => $photo->id]);
        $this->artisan('photos:expire')->assertSuccessful();
        $this->assertDatabaseCount('progress_photos', 0);
        Storage::disk('checkin_photos')->assertMissing($path);
        $metadata = app(TemporaryPosePhotos::class)->metadata($client->id, $check->id, 'side_chest');
        $this->assertSame($deadline->toISOString(), $metadata['expires_at']);
        $this->actingAs($client)->get($this->photo($client, $check, 'side_chest'))->assertOk();
        $this->assertSame(0, $check->fresh()->photo_version);
        $this->keep($coach, $client, $check, 'side_chest')->assertOk();
    }

    public function test_check_in_deletion_removes_saved_and_temporary_files_and_retry_jobs_protect_existing_check_ins(): void
    {
        [$coach, $client, $check] = $this->state();
        $this->upload($client, $check, 'front_relaxed');
        $this->upload($client, $check, 'most_muscular');
        $this->keep($coach, $client, $check, 'front_relaxed')->assertOk();
        (new DeleteTemporaryCheckInPhotos($client->id, [$check->id]))->handle(app(TemporaryPosePhotos::class));
        $this->assertCount(4, Storage::disk('temporary_checkin_photos')->allFiles());
        $this->actingAs($client)->deleteJson('/api/clients/'.$client->id.'/daily-check-ins/'.$check->id)->assertNoContent();
        $this->assertDatabaseCount('progress_photos', 0);
        $this->assertSame([], Storage::disk('checkin_photos')->allFiles());
        $this->assertSame([], Storage::disk('temporary_checkin_photos')->allFiles());
    }

    private function state(): array
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $client = User::factory()->create();
        $coach->clients()->attach($client, ['status' => 'active', 'start_date' => today()->toDateString()]);
        $check = $client->dailyCheckIns()->create(['check_in_date' => today(), 'bodyweight' => 81.4, 'notes' => 'History stays.']);

        return [$coach, $client, $check];
    }

    private function base(User $client, DailyCheckIn $check): string
    {
        return '/api/clients/'.$client->id.'/daily-check-ins/'.$check->id.'/posing';
    }

    private function photo(User $client, DailyCheckIn $check, string $pose): string
    {
        return $this->base($client, $check).'/photos/'.$pose;
    }

    private function upload(User $client, DailyCheckIn $check, string $pose): void
    {
        $this->actingAs($client)->post($this->photo($client, $check, $pose), [
            'photo_version' => $check->fresh()->photo_version,
            'photo' => UploadedFile::fake()->createWithContent('pose.png', base64_decode(self::PNG)),
        ], ['Accept' => 'application/json'])->assertOk();
    }

    private function keep(User $coach, User $client, DailyCheckIn $check, string $pose, bool $retained = true): TestResponse
    {
        return $this->actingAs($coach)->putJson($this->photo($client, $check, $pose).'/retention', [
            'photo_version' => $check->fresh()->photo_version, 'retained' => $retained,
        ]);
    }
}
