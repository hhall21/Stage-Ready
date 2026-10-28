<?php

namespace App\Services;

use App\Models\DailyCheckIn;
use App\Models\User;
use App\Support\BodybuildingPoses;
use App\Support\CheckInPhotoCleanup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CoachPhotoRetention
{
    public function __construct(private TemporaryPosePhotos $temporary) {}

    /** Called while holding the check-in photo lock and coach/client database locks. */
    public function select(DailyCheckIn $record, string $pose, User $coach, bool $retained): void
    {
        foreach ($record->posePhotos()->lockForUpdate()->get() as $photo) {
            if ($photo->coach_viewed_at === null || ! $photo->expires_at?->isFuture()) {
                if ($photo->expires_at?->isFuture()) {
                    $this->temporary->importLegacy($record, $photo);
                }
                $path = $photo->file_path;
                $photo->delete();
                CheckInPhotoCleanup::afterCommit([$path]);
            }
        }
        $existing = $record->posePhotos()->where('pose_key', $pose)->first();
        if (! $retained) {
            if ($existing !== null) {
                $path = $existing->file_path;
                $existing->delete();
                CheckInPhotoCleanup::afterCommit([$path]);
            }

            return;
        }
        if ($existing !== null) {
            return;
        }
        abort_if($record->posePhotos()->count() >= 4, 409, 'You can keep up to four photos per check-in. Remove a saved copy before choosing another.');
        $metadata = $this->temporary->viewed($record, $pose);
        $viewedAt = CarbonImmutable::parse($metadata['coach_viewed_at']);
        $expiresAt = $viewedAt->addDays(7);
        abort_unless($expiresAt->isFuture(), 409, 'The seven-day saving period for this photo has ended.');
        $extension = match ($metadata['mime_type']) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            default => throw new RuntimeException('Unsupported photo format.'),
        };
        $path = $record->client_id.'/'.$record->id.'/'.Str::uuid().'.'.$extension;
        $this->temporary->stageRetainedFile($path);
        $stream = $this->temporary->disk()->readStream($metadata['file_path']);
        abort_unless(is_resource($stream), 404);
        try {
            try {
                if (! Storage::disk('checkin_photos')->put($path, $stream)) {
                    throw new RuntimeException('The selected photo could not be saved.');
                }
            } finally {
                fclose($stream);
            }
            $record->posePhotos()->create(['pose_key' => $pose, 'photo_type' => BodybuildingPoses::find($pose)['photo_type'],
                'file_path' => $path, 'mime_type' => $metadata['mime_type'], 'coach_viewed_at' => $viewedAt,
                'retained_by_coach_id' => $coach->id, 'expires_at' => $expiresAt]);
        } catch (Throwable $error) {
            CheckInPhotoCleanup::unreferenced([$path]);
            throw $error;
        }
    }
}
