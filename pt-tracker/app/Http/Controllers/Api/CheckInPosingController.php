<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePosePhotoRequest;
use App\Http\Requests\SavePosingReviewRequest;
use App\Models\CheckInPosingReview;
use App\Models\DailyCheckIn;
use App\Models\ProgressPhoto;
use App\Models\User;
use App\Services\CoachPhotoRetention;
use App\Services\TemporaryPosePhotos;
use App\Support\BodybuildingPoses;
use App\Support\CheckInPhotoCleanup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CheckInPosingController extends Controller
{
    public function __construct(private TemporaryPosePhotos $temporary) {}

    public function show(User $client, int $checkIn): JsonResponse
    {
        Gate::authorize('useCheckInPhotos', $client);

        return $this->temporary->locked($client->id, $checkIn, function () use ($client, $checkIn): JsonResponse {
            Gate::authorize('useCheckInPhotos', $client);

            return $this->response($client->dailyCheckIns()->findOrFail($checkIn));
        });
    }

    public function storePhoto(SavePosePhotoRequest $request, User $client, int $checkIn, string $pose): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        Gate::authorize('useCheckInPhotos', $client);
        $client->dailyCheckIns()->findOrFail($checkIn);
        BodybuildingPoses::find($pose);
        $file = $request->file('photo');
        abort_unless($file instanceof UploadedFile, 422);

        return $this->temporary->locked($client->id, $checkIn, function () use ($request, $client, $checkIn, $pose, $file): JsonResponse {
            $record = DB::transaction(function () use ($request, $client, $checkIn, $pose, $file): DailyCheckIn {
                $lockedClient = $this->lockClient($request, $client);
                $record = $lockedClient->dailyCheckIns()->lockForUpdate()->findOrFail($checkIn);
                $this->assertVersion($request, $record);
                $this->temporary->store($record, $pose, $file);
                $retained = $record->posePhotos()->where('pose_key', $pose)->first();
                if ($retained !== null) {
                    $path = $retained->file_path;
                    $retained->delete();
                    CheckInPhotoCleanup::afterCommit([$path]);
                }
                $this->invalidate($record);

                return $record;
            });

            return $this->response($record);
        });
    }

    public function destroyPhoto(Request $request, User $client, int $checkIn, string $pose): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        Gate::authorize('useCheckInPhotos', $client);
        BodybuildingPoses::find($pose);
        $request->validate(['photo_version' => ['required', 'integer', 'min:0']]);

        return $this->temporary->locked($client->id, $checkIn, function () use ($request, $client, $checkIn, $pose): JsonResponse {
            $record = DB::transaction(function () use ($request, $client, $checkIn, $pose): DailyCheckIn {
                $lockedClient = $this->lockClient($request, $client);
                $record = $lockedClient->dailyCheckIns()->lockForUpdate()->findOrFail($checkIn);
                $this->assertVersion($request, $record);
                $photo = $record->posePhotos()->where('pose_key', $pose)->first();
                $temporary = $this->temporary->metadata($client->id, $checkIn, $pose);
                abort_if($photo === null && $temporary === null, 404);
                if ($photo !== null) {
                    $path = $photo->file_path;
                    $photo->delete();
                    CheckInPhotoCleanup::afterCommit([$path]);
                }
                if ($temporary !== null) {
                    $this->temporary->remove($record, $pose);
                }
                $this->invalidate($record);

                return $record;
            });

            return $this->response($record);
        });
    }

    public function submit(Request $request, User $client, int $checkIn): JsonResponse
    {
        Gate::authorize('manageCheckIns', $client);
        Gate::authorize('useCheckInPhotos', $client);
        $request->validate(['photo_version' => ['required', 'integer', 'min:0']]);

        return $this->temporary->locked($client->id, $checkIn, function () use ($request, $client, $checkIn): JsonResponse {
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
        });
    }

    public function review(SavePosingReviewRequest $request, User $client, int $checkIn): JsonResponse
    {
        $coach = $request->user();
        abort_unless($coach?->role === 'coach', 403);
        Gate::authorize('useCheckInPhotos', $client);

        return $this->temporary->locked($client->id, $checkIn, function () use ($request, $coach, $client, $checkIn): JsonResponse {
            $record = DB::transaction(function () use ($request, $coach, $client, $checkIn): DailyCheckIn {
                // Keep the coach/client lock order shared with ending coaching.
                $lockedCoach = User::whereKey($coach->id)->lockForUpdate()->firstOrFail();
                $lockedClient = User::whereKey($client->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedCoach->role === 'coach', 403);
                Gate::forUser($lockedCoach)->authorize('useCheckInPhotos', $lockedClient);
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
        });
    }

    public function photo(Request $request, ProgressPhoto $photo): StreamedResponse
    {
        $checkIn = $photo->checkIn()->firstOrFail();
        Gate::authorize('useCheckInPhotos', $checkIn->client()->firstOrFail());
        abort_unless($photo->expires_at?->isFuture(), 404);
        abort_unless($photo->coach_viewed_at !== null && $photo->pose_key !== null
            && in_array($photo->pose_key, BodybuildingPoses::keys(), true)
            && in_array($photo->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        $disk = Storage::disk('checkin_photos');
        abort_unless($disk->exists($photo->file_path), 404);
        $stream = $disk->readStream($photo->file_path);
        abort_unless(is_resource($stream), 404);

        return $this->streamPhoto($request, $checkIn, $photo->pose_key, $photo->mime_type, $stream);
    }

    public function temporaryPhoto(Request $request, User $client, int $checkIn, string $pose): StreamedResponse
    {
        Gate::authorize('useCheckInPhotos', $client);
        BodybuildingPoses::find($pose);

        return $this->temporary->locked($client->id, $checkIn, function () use ($request, $client, $checkIn, $pose): StreamedResponse {
            Gate::authorize('useCheckInPhotos', $client);
            $record = $client->dailyCheckIns()->findOrFail($checkIn);
            $retained = $record->posePhotos()->available()->where('pose_key', $pose)->first();
            if ($retained !== null) {
                return $this->photo($request, $retained);
            }
            $metadata = $this->temporary->available($client->id, $checkIn, $pose, $record->photo_version);
            abort_if($metadata === null, 404);
            $stream = $this->temporary->disk()->readStream($metadata['file_path']);
            abort_unless(is_resource($stream), 404);
            if ($request->user()?->role === 'coach' && ($metadata['coach_viewed_at'] ?? null) === null) {
                DB::transaction(function () use ($request, $client, $checkIn, $pose): void {
                    $coach = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                    $lockedClient = User::whereKey($client->id)->lockForUpdate()->firstOrFail();
                    Gate::forUser($coach)->authorize('useCheckInPhotos', $lockedClient);
                    $latest = $lockedClient->dailyCheckIns()->lockForUpdate()->findOrFail($checkIn);
                    $this->temporary->viewed($latest, $pose);
                });
            }

            return $this->streamPhoto($request, $record, $pose, $metadata['mime_type'], $stream);
        });
    }

    public function retention(Request $request, User $client, int $checkIn, string $pose, CoachPhotoRetention $retention): JsonResponse
    {
        $coach = $request->user();
        abort_unless($coach?->role === 'coach', 403);
        $request->validate(['retained' => ['required', 'boolean']]);

        return $this->coachPhotoAction($request, $client, $checkIn, $pose,
            fn (DailyCheckIn $record) => $retention->select($record, $pose, $coach, $request->boolean('retained')));
    }

    /** @param \Closure(DailyCheckIn): void $action */
    private function coachPhotoAction(Request $request, User $client, int $checkIn, string $pose, \Closure $action): JsonResponse
    {
        $coach = $request->user();
        abort_unless($coach?->role === 'coach', 403);
        Gate::authorize('useCheckInPhotos', $client);
        BodybuildingPoses::find($pose);
        $request->validate(['photo_version' => ['required', 'integer', 'min:0']]);

        return $this->temporary->locked($client->id, $checkIn, function () use ($request, $coach, $client, $checkIn, $action): JsonResponse {
            $record = DB::transaction(function () use ($request, $coach, $client, $checkIn, $action): DailyCheckIn {
                $lockedCoach = User::whereKey($coach->id)->lockForUpdate()->firstOrFail();
                $lockedClient = User::whereKey($client->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($lockedCoach)->authorize('useCheckInPhotos', $lockedClient);
                $record = $lockedClient->dailyCheckIns()->lockForUpdate()->findOrFail($checkIn);
                $this->assertVersion($request, $record);
                $action($record);

                return $record;
            });

            return $this->response($record);
        });
    }

    /** @param resource $stream */
    private function streamPhoto(Request $request, DailyCheckIn $checkIn, string $pose, string $mime, $stream): StreamedResponse
    {
        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            default => abort(404),
        };
        $filename = 'stage-ready-'.$pose.'-'.$checkIn->check_in_date->toDateString().'.'.$extension;

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Vary' => 'Cookie',
            'Content-Disposition' => HeaderUtils::makeDisposition($request->boolean('download') ? 'attachment' : 'inline', $filename),
        ]);
    }

    private function lockClient(Request $request, User $client): User
    {
        $lockedClient = User::whereKey($client->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($lockedClient)->authorize('manageCheckIns', $lockedClient);
        Gate::forUser($lockedClient)->authorize('useCheckInPhotos', $lockedClient);
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
        $photos = collect($this->temporary->photos($record))->keyBy('pose_key');
        foreach ($record->posePhotos()->available()->orderBy('id')->get() as $photo) {
            $photos->put($photo->pose_key, ['id' => $photo->id, 'pose_key' => $photo->pose_key,
                'url' => '/api/clients/'.$record->client_id.'/daily-check-ins/'.$record->id.'/posing/photos/'.$photo->pose_key,
                'updated_at' => $photo->updated_at?->toISOString(), 'expires_at' => $photo->expires_at?->toISOString(),
                'coach_viewed_at' => $photo->coach_viewed_at?->toISOString(), 'retained' => true]);
        }

        return response()->json(['data' => [
            'check_in_id' => $record->id,
            'photo_version' => $record->photo_version,
            'submitted_at' => $record->submitted_at?->toISOString(),
            'standard_poses' => BodybuildingPoses::standard(),
            'additional_poses' => BodybuildingPoses::additional(),
            'photos' => $photos->values()->all(),
            'retained_photo_limit' => 4,
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
