<?php

namespace Tests\Feature;

use App\Models\DailyCheckIn;
use App\Models\ProgressPhoto;
use App\Models\TrainerClient;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class PosingCheckInTest extends TestCase
{
    use RefreshDatabase;

    private const POSES = [
        'front_double_biceps', 'front_lat_spread', 'side_chest', 'back_double_biceps',
        'back_lat_spread', 'side_triceps', 'abdominals_and_thighs', 'most_muscular',
    ];

    private string $photoStorageRoot;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->photoStorageRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'stage-ready-posing-'.bin2hex(random_bytes(8));
        $originalStorage = $this->app->storagePath();
        $this->app->useStoragePath($this->photoStorageRoot);
        try {
            Storage::fake('checkin_photos');
        } finally {
            $this->app->useStoragePath($originalStorage);
        }
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->photoStorageRoot) && is_dir($this->photoStorageRoot)) {
                if (realpath(dirname($this->photoStorageRoot)) !== realpath(sys_get_temp_dir())
                    || ! str_starts_with(basename($this->photoStorageRoot), 'stage-ready-posing-')) {
                    throw new RuntimeException('Unexpected posing-test cleanup path.');
                }
                (new Filesystem)->deleteDirectory($this->photoStorageRoot);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_date_only_check_ins_accept_pose_submissions_but_cannot_create_a_coach_rating(): void
    {
        $client = User::factory()->create();
        $metrics = '/api/clients/'.$client->id.'/daily-check-ins';
        $this->actingAs($client)->postJson($metrics, [
            'check_in_date' => today()->toDateString(), 'bodyweight' => 81.4,
            'posing_rating' => 10, 'feedback' => 'Client-supplied review',
            'is_competition_prep' => true, 'flatness_rating' => 9, 'fullness_rating' => 9,
        ])->assertUnprocessable()->assertJsonValidationErrors('posing_rating');
        $this->assertDatabaseCount('daily_check_ins', 0);
        $this->postJson($metrics, [
            'check_in_date' => today()->toDateString(),
        ])->assertCreated()->assertJsonPath('data.bodyweight', null);
        $checkIn = $client->dailyCheckIns()->firstOrFail();
        $this->patchJson($metrics.'/'.$checkIn->id, [
            'photo_version' => 10, 'submitted_at' => now()->toIso8601String(),
            'posing_rating' => 10, 'feedback' => 'Client-supplied review',
        ])->assertUnprocessable()->assertJsonValidationErrors(['photo_version', 'submitted_at', 'posing_rating']);
        $response = $this->getJson($this->base($client, $checkIn))->assertOk()
            ->assertJsonPath('data.check_in_id', $checkIn->id)
            ->assertJsonPath('data.photo_version', 0)->assertJsonPath('data.submitted_at', null)
            ->assertJsonCount(0, 'data.photos')->assertJsonCount(0, 'data.reviews')
            ->assertJsonCount(8, 'data.required_poses');
        $this->assertSame(self::POSES, array_column($response->json('data.required_poses'), 'key'));
        foreach ($response->json('data.required_poses') as $pose) {
            $this->assertNotEmpty($pose['label']);
            $this->assertContains($pose['photo_type'], ['front', 'side', 'back', 'other']);
        }
        $this->assertDatabaseCount('check_in_posing_reviews', 0);
        $this->completePhotos($client, $checkIn);
        $this->submit($client, $checkIn)->assertJsonCount(8, 'data.photos');
        $this->assertNull($checkIn->fresh()->bodyweight);
    }

    public function test_submission_requires_each_of_the_eight_distinct_poses(): void
    {
        [$client, $checkIn] = $this->clientCheckIn();
        $this->actingAs($client)->postJson($this->base($client, $checkIn).'/submit', ['photo_version' => 0])
            ->assertUnprocessable();
        foreach (array_slice(self::POSES, 0, 7) as $pose) {
            $this->upload($client, $checkIn, $pose)->assertSuccessful();
        }
        $this->postJson($this->base($client, $checkIn).'/submit', ['photo_version' => 7])->assertUnprocessable();
        $this->upload($client, $checkIn, self::POSES[0])->assertSuccessful();
        $this->assertSame(7, $checkIn->progressPhotos()->count());
        $this->postJson($this->base($client, $checkIn).'/submit', ['photo_version' => 8])->assertUnprocessable();
        $this->upload($client, $checkIn, self::POSES[7])->assertSuccessful();
        $this->postJson($this->base($client, $checkIn).'/submit', ['photo_version' => 9])->assertOk()
            ->assertJsonCount(8, 'data.photos')->assertJsonPath('data.photo_version', 9);
        $this->assertNotNull($checkIn->fresh()->getAttribute('submitted_at'));
        $this->assertCount(8, Storage::disk('checkin_photos')->allFiles());
    }

    public function test_posing_records_are_scoped_and_coaches_cannot_change_client_photos(): void
    {
        [$client, $checkIn] = $this->clientCheckIn();
        [$other, $foreign] = $this->clientCheckIn();
        $base = $this->base($client, $checkIn);
        $this->getJson($base)->assertUnauthorized();
        $this->actingAs($other)->getJson($base)->assertForbidden();
        $wrongScope = $this->base($client, $foreign);
        $this->actingAs($client)->getJson($wrongScope)->assertNotFound();
        $this->post($wrongScope.'/photos/'.self::POSES[0], [
            'photo_version' => 0, 'photo' => $this->image(),
        ], ['Accept' => 'application/json'])->assertNotFound();
        $coach = $this->coachFor($client);
        $this->actingAs($coach)->getJson($base)->assertOk();
        $this->post($base.'/photos/'.self::POSES[0], [
            'photo_version' => 0, 'photo' => $this->image(),
        ], ['Accept' => 'application/json'])->assertForbidden();
        $this->deleteJson($base.'/photos/'.self::POSES[0], ['photo_version' => 0])->assertForbidden();
        $this->postJson($base.'/submit', ['photo_version' => 0])->assertForbidden();
        $this->assertDatabaseCount('progress_photos', 0);
    }

    public function test_private_images_require_current_access_and_never_expose_storage_paths(): void
    {
        [$client, $checkIn] = $this->clientCheckIn();
        $coach = $this->coachFor($client);
        $response = $this->upload($client, $checkIn, self::POSES[0])->assertSuccessful()
            ->assertJsonMissingPath('data.photos.0.file_path');
        $url = $response->json('data.photos.0.url');
        $this->assertStringContainsString('/api/daily-check-in-photos/', $url);
        $this->assertStringNotContainsString('/storage/', $url);
        $photo = $this->getJson($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('private', $photo->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $photo->headers->get('Cache-Control'));
        $storedPhoto = $checkIn->progressPhotos()->firstOrFail();
        $this->assertSame(Storage::disk('checkin_photos')->get($storedPhoto->file_path), $photo->streamedContent());
        $this->actingAs($coach)->getJson($url)->assertOk();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'coach']))->getJson($url)->assertForbidden();
        $coach->forceFill(['is_active' => false])->save();
        $this->actingAs($coach->fresh())->getJson($url)->assertForbidden();
        $this->assertGuest();
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($client)->getJson($url)->assertOk();
        $this->getJson('/api/daily-check-in-photos/999999')->assertNotFound();
    }

    public function test_invalid_pose_keys_and_files_cannot_change_a_draft(): void
    {
        [$client, $checkIn] = $this->clientCheckIn();
        $this->upload($client, $checkIn, 'front')->assertNotFound();
        $invalid = [
            UploadedFile::fake()->createWithContent('pose.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            UploadedFile::fake()->createWithContent('pose.jpg', 'This is not an image.'),
            UploadedFile::fake()->image('pose.gif', 50, 50),
            $this->image()->size(5121),
            UploadedFile::fake()->image('pose.png', 6001, 1),
        ];
        foreach ($invalid as $file) {
            $this->upload($client, $checkIn, self::POSES[0], 0, $file)->assertUnprocessable()
                ->assertJsonValidationErrors('photo');
        }
        $this->assertSame(0, $checkIn->fresh()->getAttribute('photo_version'));
        $this->assertDatabaseCount('progress_photos', 0);
        $this->assertSame([], Storage::disk('checkin_photos')->allFiles());
        foreach (['jpg', 'png', 'webp'] as $index => $extension) {
            $this->upload($client, $checkIn, self::POSES[$index], null, $this->image('pose.'.$extension))
                ->assertSuccessful();
        }
    }

    public function test_only_assigned_coaches_can_review_a_complete_submitted_set(): void
    {
        [$client, $checkIn] = $this->clientCheckIn();
        $coach = $this->coachFor($client);
        $this->actingAs($coach)->putJson($this->base($client, $checkIn).'/review', $this->reviewData($checkIn))
            ->assertUnprocessable();
        $this->completePhotos($client, $checkIn);
        $this->actingAs($coach)->putJson($this->base($client, $checkIn).'/review', $this->reviewData($checkIn))
            ->assertUnprocessable();
        $this->submit($client, $checkIn);
        $url = $this->base($client, $checkIn).'/review';
        $this->actingAs($client)->putJson($url, $this->reviewData($checkIn))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'coach']))->putJson($url, $this->reviewData($checkIn))
            ->assertForbidden();
        $this->actingAs($coach)->putJson($url, $this->reviewData($checkIn))->assertOk()
            ->assertJsonCount(1, 'data.reviews')->assertJsonPath('data.reviews.0.coach_id', $coach->id)
            ->assertJsonPath('data.reviews.0.posing_rating', 7)->assertJsonPath('data.reviews.0.is_current', true);
    }

    public function test_review_scores_feedback_and_prep_only_conditioning_are_validated(): void
    {
        [$client, $checkIn] = $this->clientCheckIn();
        $coach = $this->coachFor($client);
        $this->completePhotos($client, $checkIn);
        $this->submit($client, $checkIn);
        $url = $this->base($client, $checkIn).'/review';
        $invalid = [
            ['posing_rating' => 0], ['posing_rating' => 11], ['posing_rating' => 7.5],
            ['feedback' => ''], ['feedback' => str_repeat('a', 10001)],
            ['is_competition_prep' => true, 'flatness_rating' => 0, 'fullness_rating' => 8],
            ['is_competition_prep' => true, 'flatness_rating' => 8, 'fullness_rating' => 11],
            ['flatness_rating' => 6, 'fullness_rating' => 7],
        ];
        foreach ($invalid as $changes) {
            $this->actingAs($coach)->putJson($url, $this->reviewData($checkIn, $changes))->assertUnprocessable();
        }
        $this->assertDatabaseCount('check_in_posing_reviews', 0);
        $this->putJson($url, $this->reviewData($checkIn, ['is_competition_prep' => true]))->assertOk()
            ->assertJsonPath('data.reviews.0.flatness_rating', null)
            ->assertJsonPath('data.reviews.0.fullness_rating', null);
        $this->putJson($url, $this->reviewData($checkIn, [
            'posing_rating' => 10, 'feedback' => str_repeat('a', 10000), 'is_competition_prep' => true,
            'flatness_rating' => 1, 'fullness_rating' => 10,
        ]))->assertOk()->assertJsonPath('data.reviews.0.flatness_rating', 1)
            ->assertJsonPath('data.reviews.0.fullness_rating', 10);
        $optionalPrep = $this->reviewData($checkIn, ['is_competition_prep' => true]);
        unset($optionalPrep['flatness_rating'], $optionalPrep['fullness_rating']);
        $this->putJson($url, $optionalPrep)->assertOk()
            ->assertJsonPath('data.reviews.0.flatness_rating', null)
            ->assertJsonPath('data.reviews.0.fullness_rating', null);
        $this->putJson($url, $this->reviewData($checkIn))->assertOk()
            ->assertJsonPath('data.reviews.0.is_competition_prep', false)
            ->assertJsonPath('data.reviews.0.flatness_rating', null)
            ->assertJsonPath('data.reviews.0.fullness_rating', null);
        $this->assertDatabaseCount('check_in_posing_reviews', 1);
    }

    public function test_coach_reviews_are_independent_and_survive_the_end_of_coaching(): void
    {
        [$client, $checkIn] = $this->clientCheckIn();
        $first = $this->coachFor($client);
        $second = $this->coachFor($client);
        $this->completePhotos($client, $checkIn);
        $this->submit($client, $checkIn);
        $base = $this->base($client, $checkIn);
        $this->actingAs($first)->putJson($base.'/review', $this->reviewData($checkIn, ['posing_rating' => 8]))
            ->assertOk();
        $this->actingAs($second)->putJson($base.'/review', $this->reviewData($checkIn, ['posing_rating' => 4]))
            ->assertOk();
        $response = $this->actingAs($first)->putJson($base.'/review', $this->reviewData($checkIn, [
            'posing_rating' => 9, 'coach_id' => $second->id,
        ]))
            ->assertOk()->assertJsonCount(2, 'data.reviews');
        $reviews = collect($response->json('data.reviews'))->keyBy('coach_id');
        $this->assertSame(9, $reviews[$first->id]['posing_rating']);
        $this->assertSame(4, $reviews[$second->id]['posing_rating']);
        $this->assertSame($first->name, $reviews[$first->id]['coach_name']);
        $url = $response->json('data.photos.0.url');
        $this->deleteJson('/api/clients/'.$client->id.'/coaching')->assertOk();
        $this->getJson($url)->assertForbidden();
        $this->getJson($base)->assertForbidden();
        $this->actingAs($client)->getJson($base)->assertOk()->assertJsonCount(2, 'data.reviews');
        $this->getJson($url)->assertOk();
        $this->actingAs($second)->getJson($url)->assertOk();
        $this->assertDatabaseCount('check_in_posing_reviews', 2);
    }

    public function test_photo_changes_invalidate_submission_and_reject_stale_mutations(): void
    {
        [$client, $checkIn] = $this->clientCheckIn();
        $coach = $this->coachFor($client);
        $this->completePhotos($client, $checkIn);
        $this->submit($client, $checkIn);
        $base = $this->base($client, $checkIn);
        $this->actingAs($coach)->putJson($base.'/review', $this->reviewData($checkIn))->assertOk();
        $oldPhoto = $checkIn->progressPhotos()->where('pose_key', self::POSES[0])->firstOrFail();
        $oldPath = $oldPhoto->file_path;
        $this->upload($client, $checkIn, self::POSES[0], 8)->assertSuccessful()
            ->assertJsonPath('data.photo_version', 9)->assertJsonPath('data.submitted_at', null)
            ->assertJsonPath('data.reviews.0.photo_version', 8)->assertJsonPath('data.reviews.0.is_current', false);
        Storage::disk('checkin_photos')->assertMissing($oldPath);
        $files = Storage::disk('checkin_photos')->allFiles();
        $this->upload($client, $checkIn, self::POSES[1], 8)->assertConflict();
        $this->deleteJson($base.'/photos/'.self::POSES[1], ['photo_version' => 8])->assertConflict();
        $this->postJson($base.'/submit', ['photo_version' => 8])->assertConflict();
        $this->actingAs($coach)->putJson($base.'/review', $this->reviewData($checkIn, ['photo_version' => 8]))
            ->assertConflict();
        $this->assertSame($files, Storage::disk('checkin_photos')->allFiles());
        $this->submit($client, $checkIn);
        $this->deleteJson($base.'/photos/'.self::POSES[0], ['photo_version' => 9])->assertOk()
            ->assertJsonPath('data.photo_version', 10)->assertJsonPath('data.submitted_at', null)
            ->assertJsonCount(7, 'data.photos')->assertJsonCount(1, 'data.reviews');
        $this->assertCount(7, Storage::disk('checkin_photos')->allFiles());
    }

    public function test_photo_mutations_require_an_explicit_current_version(): void
    {
        [$client, $checkIn] = $this->clientCheckIn();
        $base = $this->base($client, $checkIn);
        $this->actingAs($client)->post($base.'/photos/'.self::POSES[0], [
            'photo' => $this->image(),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('photo_version');
        $this->deleteJson($base.'/photos/'.self::POSES[0])->assertUnprocessable()
            ->assertJsonValidationErrors('photo_version');
        $this->postJson($base.'/submit')->assertUnprocessable()->assertJsonValidationErrors('photo_version');
        $coach = $this->coachFor($client);
        $payload = $this->reviewData($checkIn);
        unset($payload['photo_version']);
        $this->actingAs($coach)->putJson($base.'/review', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors('photo_version');
        $this->assertSame(0, $checkIn->fresh()->getAttribute('photo_version'));
        $this->assertSame([], Storage::disk('checkin_photos')->allFiles());
    }

    public function test_changing_the_check_in_date_invalidates_reviews_but_notes_only_edits_do_not(): void
    {
        [$client, $checkIn] = $this->clientCheckIn();
        $coach = $this->coachFor($client);
        $this->completePhotos($client, $checkIn);
        $this->submit($client, $checkIn);
        $base = $this->base($client, $checkIn);
        $this->actingAs($coach)->putJson($base.'/review', $this->reviewData($checkIn))->assertOk();
        $metrics = '/api/clients/'.$client->id.'/daily-check-ins/'.$checkIn->id;
        $this->actingAs($client)->patchJson($metrics, ['notes' => 'A correction to my notes.'])->assertOk();
        $this->getJson($base)->assertOk()->assertJsonPath('data.photo_version', 8)
            ->assertJsonPath('data.reviews.0.is_current', true);
        $this->assertNotNull($checkIn->fresh()->getAttribute('submitted_at'));
        $this->patchJson($metrics, ['check_in_date' => today()->subDay()->toDateString()])->assertOk();
        $this->getJson($base)->assertOk()->assertJsonPath('data.photo_version', 9)
            ->assertJsonPath('data.submitted_at', null)->assertJsonPath('data.reviews.0.is_current', false);
    }

    public function test_deleting_a_check_in_removes_its_private_files_and_review_rows(): void
    {
        [$client, $checkIn] = $this->clientCheckIn();
        $coach = $this->coachFor($client);
        $this->completePhotos($client, $checkIn);
        $this->submit($client, $checkIn);
        $this->actingAs($coach)->putJson($this->base($client, $checkIn).'/review', $this->reviewData($checkIn))
            ->assertOk();
        $this->actingAs($client)->deleteJson('/api/clients/'.$client->id.'/daily-check-ins/'.$checkIn->id)
            ->assertNoContent();
        $this->assertDatabaseMissing('daily_check_ins', ['id' => $checkIn->id]);
        $this->assertDatabaseCount('progress_photos', 0);
        $this->assertDatabaseCount('check_in_posing_reviews', 0);
        $this->assertSame([], Storage::disk('checkin_photos')->allFiles());
    }

    public function test_a_failed_photo_transaction_preserves_the_old_file_and_removes_the_new_upload(): void
    {
        [$client, $checkIn] = $this->clientCheckIn();
        $this->upload($client, $checkIn, self::POSES[0])->assertSuccessful();
        $oldPhoto = $checkIn->progressPhotos()->firstOrFail();
        $originalDispatcher = ProgressPhoto::getEventDispatcher();
        ProgressPhoto::setEventDispatcher(clone $originalDispatcher);
        ProgressPhoto::saving(function (): void {
            throw new RuntimeException('Simulated photo database failure.');
        });
        $this->withoutExceptionHandling();
        try {
            $this->upload($client, $checkIn, self::POSES[0]);
            $this->fail('Expected the photo transaction to fail.');
        } catch (RuntimeException $error) {
            $this->assertSame('Simulated photo database failure.', $error->getMessage());
        } finally {
            ProgressPhoto::setEventDispatcher($originalDispatcher);
            $this->withExceptionHandling();
        }
        $this->assertSame($oldPhoto->file_path, $oldPhoto->fresh()->file_path);
        $this->assertSame(1, $checkIn->fresh()->getAttribute('photo_version'));
        $this->assertSame([$oldPhoto->file_path], Storage::disk('checkin_photos')->allFiles());
    }

    public function test_a_failed_check_in_delete_preserves_its_database_record_and_file(): void
    {
        [$client, $checkIn] = $this->clientCheckIn();
        $this->upload($client, $checkIn, self::POSES[0])->assertSuccessful();
        $photo = $checkIn->progressPhotos()->firstOrFail();
        $originalDispatcher = DailyCheckIn::getEventDispatcher();
        DailyCheckIn::setEventDispatcher(clone $originalDispatcher);
        DailyCheckIn::deleting(function (): void {
            throw new RuntimeException('Simulated check-in database failure.');
        });
        $this->withoutExceptionHandling();
        try {
            $this->deleteJson('/api/clients/'.$client->id.'/daily-check-ins/'.$checkIn->id);
            $this->fail('Expected the check-in transaction to fail.');
        } catch (RuntimeException $error) {
            $this->assertSame('Simulated check-in database failure.', $error->getMessage());
        } finally {
            DailyCheckIn::setEventDispatcher($originalDispatcher);
            $this->withExceptionHandling();
        }
        $this->assertDatabaseHas('daily_check_ins', ['id' => $checkIn->id]);
        $this->assertDatabaseHas('progress_photos', ['id' => $photo->id]);
        Storage::disk('checkin_photos')->assertExists($photo->file_path);
    }

    /** @return array{User, DailyCheckIn} */
    private function clientCheckIn(): array
    {
        $client = User::factory()->create();

        return [$client, $client->dailyCheckIns()->create(['check_in_date' => today(), 'bodyweight' => 81.4])];
    }

    private function coachFor(User $client): User
    {
        $coach = User::factory()->create(['role' => 'coach']);
        TrainerClient::create(['trainer_id' => $coach->id, 'client_id' => $client->id, 'start_date' => today()]);

        return $coach;
    }

    private function base(User $client, DailyCheckIn $checkIn): string
    {
        return '/api/clients/'.$client->id.'/daily-check-ins/'.$checkIn->id.'/posing';
    }

    private function image(string $name = 'pose.jpg'): UploadedFile
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD is required to generate image upload fixtures.');
        }

        return UploadedFile::fake()->image($name, 100, 150);
    }

    private function upload(User $client, DailyCheckIn $checkIn, string $pose, ?int $version = null, ?UploadedFile $photo = null): TestResponse
    {
        return $this->actingAs($client)->post($this->base($client, $checkIn).'/photos/'.$pose, [
            'photo_version' => $version ?? $checkIn->fresh()->getAttribute('photo_version'),
            'photo' => $photo ?? $this->image(),
        ], ['Accept' => 'application/json']);
    }

    private function completePhotos(User $client, DailyCheckIn $checkIn): void
    {
        foreach (self::POSES as $pose) {
            $this->upload($client, $checkIn, $pose)->assertSuccessful();
        }
    }

    private function submit(User $client, DailyCheckIn $checkIn): TestResponse
    {
        return $this->actingAs($client)->postJson($this->base($client, $checkIn).'/submit', [
            'photo_version' => $checkIn->fresh()->getAttribute('photo_version'),
        ])->assertOk();
    }

    /** @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function reviewData(DailyCheckIn $checkIn, array $changes = []): array
    {
        return array_replace([
            'photo_version' => $checkIn->fresh()->getAttribute('photo_version'),
            'posing_rating' => 7, 'feedback' => 'Keep the transitions controlled and repeat the same pose setup.',
            'is_competition_prep' => false, 'flatness_rating' => null, 'fullness_rating' => null,
        ], $changes);
    }
}
