<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePosePhotoRequest;
use App\Http\Requests\SavePosingReviewRequest;
use App\Models\CheckInPosingReview;
use App\Models\DailyCheckIn;
use App\Models\ProgressPhoto;
use App\Models\User;
use App\Support\BodybuildingPoses;
use App\Support\CheckInPhotoCleanup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CheckInPosingController extends Controller
{
    public function show(User $client, int $checkIn): JsonResponse
    {
        Gate::authorize('viewTracker', $client);

        return $this->response($client->dailyCheckIns()->findOrFail($checkIn));
    }

    public function storePhoto(SavePosePhotoRequest $request, User $client, int $checkIn, string $pose): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        $client->dailyCheckIns()->findOrFail($checkIn);
        $poseDefinition = BodybuildingPoses::find($pose);
        $file = $request->file('photo');
        abort_unless($file instanceof UploadedFile, 422);
        $path = $file->storeAs($client->id.'/'.$checkIn, Str::uuid().'.'.$file->extension(), 'checkin_photos');
        abort_unless(is_string($path), 500, 'The photo could not be saved.');

        try {
            $record = DB::transaction(function () use ($request, $client, $checkIn, $pose, $poseDefinition, $file, $path): DailyCheckIn {
                $lockedClient = $this->lockClient($request, $client);
                $record = $lockedClient->dailyCheckIns()->lockForUpdate()->findOrFail($checkIn);
                $this->assertVersion($request, $record);
                $existing = $record->posePhotos()->where('pose_key', $pose)->first();
                $oldPath = $existing?->file_path;
                $record->posePhotos()->updateOrCreate(['pose_key' => $pose], [
                    'photo_type' => $poseDefinition['photo_type'],
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                ]);
                $this->invalidate($record);
                if (is_string($oldPath)) {
                    CheckInPhotoCleanup::afterCommit([$oldPath]);
                }

                return $record;
            });
        } catch (Throwable $exception) {
            CheckInPhotoCleanup::unreferenced([$path]);
            throw $exception;
        }

        return $this->response($record);
    }

    public function destroyPhoto(Request $request, User $client, int $checkIn, string $pose): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        BodybuildingPoses::find($pose);
        $request->validate(['photo_version' => ['required', 'integer', 'min:0']]);
        $record = DB::transaction(function () use ($request, $client, $checkIn, $pose): DailyCheckIn {
            $lockedClient = $this->lockClient($request, $client);
            $record = $lockedClient->dailyCheckIns()->lockForUpdate()->findOrFail($checkIn);
            $this->assertVersion($request, $record);
            $photo = $record->posePhotos()->where('pose_key', $pose)->firstOrFail();
            $path = $photo->file_path;
            $photo->delete();
            $this->invalidate($record);
            CheckInPhotoCleanup::afterCommit([$path]);

            return $record;
        });

        return $this->response($record);
    }

    public function submit(Request $request, User $client, int $checkIn): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        $request->validate(['photo_version' => ['required', 'integer', 'min:0']]);
        $record = DB::transaction(function () use ($request, $client, $checkIn): DailyCheckIn {
            $lockedClient = $this->lockClient($request, $client);
            $record = $lockedClient->dailyCheckIns()->lockForUpdate()->findOrFail($checkIn);
            $this->assertVersion($request, $record);
            if ($record->submitted_at === null) {
                $record->forceFill(['submitted_at' => now()])->save();
            }

            return $record;
        });

        return $this->response($record);
    }

    public function review(SavePosingReviewRequest $request, User $client, int $checkIn): JsonResponse
    {
        $coach = $request->user();
        abort_unless($coach?->role === 'coach', 403);
        Gate::authorize('viewTracker', $client);
        $record = DB::transaction(function () use ($request, $coach, $client, $checkIn): DailyCheckIn {
            // Keep the coach/client lock order shared with ending coaching.
            $lockedCoach = User::whereKey($coach->id)->lockForUpdate()->firstOrFail();
            $lockedClient = User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedCoach->role === 'coach', 403);
            Gate::forUser($lockedCoach)->authorize('viewTracker', $lockedClient);
            $record = $lockedClient->dailyCheckIns()->lockForUpdate()->findOrFail($checkIn);
            $this->assertVersion($request, $record);
            if ($record->submitted_at === null) {
                throw ValidationException::withMessages(['check_in' => 'The client must submit this check-in before it can be reviewed.']);
            }
            $data = $request->validated();
            $data['flatness_rating'] = $request->boolean('is_competition_prep') ? ($data['flatness_rating'] ?? null) : null;
            $data['fullness_rating'] = $request->boolean('is_competition_prep') ? ($data['fullness_rating'] ?? null) : null;
            $record->posingReviews()->updateOrCreate(['coach_id' => $lockedCoach->id], [
                ...$data,
                'reviewed_at' => now(),
            ]);

            return $record;
        });

        return $this->response($record);
    }

    public function photo(ProgressPhoto $photo): StreamedResponse
    {
        $checkIn = $photo->checkIn()->firstOrFail();
        Gate::authorize('viewTracker', $checkIn->client()->firstOrFail());
        abort_unless($photo->pose_key !== null && in_array($photo->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        $disk = Storage::disk('checkin_photos');
        abort_unless($disk->exists($photo->file_path), 404);
        $stream = $disk->readStream($photo->file_path);
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $photo->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Vary' => 'Cookie',
        ]);
    }

    private function lockClient(Request $request, User $client): User
    {
        $lockedClient = User::whereKey($client->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($lockedClient)->authorize('manageCheckIns', $lockedClient);
        abort_unless($request->user()?->is($lockedClient), 403);

        return $lockedClient;
    }

    private function assertVersion(Request $request, DailyCheckIn $record): void
    {
        abort_unless($request->integer('photo_version') === $record->photo_version, 409, 'These photos changed. Reload the check-in before trying again.');
    }

    private function invalidate(DailyCheckIn $record): void
    {
        $record->forceFill(['photo_version' => $record->photo_version + 1, 'submitted_at' => null])->save();
    }

    private function response(DailyCheckIn $record): JsonResponse
    {
        return response()->json(['data' => [
            'check_in_id' => $record->id,
            'photo_version' => $record->photo_version,
            'submitted_at' => $record->submitted_at?->toISOString(),
            'standard_poses' => BodybuildingPoses::standard(),
            'additional_poses' => BodybuildingPoses::additional(),
            'photos' => $record->posePhotos()->orderBy('id')->get()->map(fn (ProgressPhoto $photo): array => [
                'id' => $photo->id,
                'pose_key' => $photo->pose_key,
                'url' => '/api/daily-check-in-photos/'.$photo->id,
                'updated_at' => $photo->updated_at?->toISOString(),
            ]),
            'reviews' => $record->posingReviews()->with('coach')->orderBy('id')->get()->map(fn (CheckInPosingReview $review): array => [
                'id' => $review->id,
                'coach_id' => $review->coach_id,
                'coach_name' => $review->coach?->name,
                'photo_version' => $review->photo_version,
                'posing_rating' => $review->posing_rating,
                'feedback' => $review->feedback,
                'is_competition_prep' => $review->is_competition_prep,
                'flatness_rating' => $review->flatness_rating,
                'fullness_rating' => $review->fullness_rating,
                'is_current' => $record->submitted_at !== null && $review->photo_version === $record->photo_version,
                'reviewed_at' => $review->reviewed_at->toISOString(),
            ]),
        ]]);
    }
}
