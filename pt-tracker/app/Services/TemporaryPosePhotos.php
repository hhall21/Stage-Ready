<?php

namespace App\Services;

use App\Jobs\DeleteTemporaryCheckInPhotos;
use App\Jobs\DeleteTemporaryPoseFiles;
use App\Models\DailyCheckIn;
use App\Models\ProgressPhoto;
use App\Support\BodybuildingPoses;
use App\Support\CheckInPhotoCleanup;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Private review files and manifests; only coach-selected copies have database photo records. */
class TemporaryPosePhotos
{
    /** @var array<string, string|null> */
    private array $rollbackManifests = [];

    /** @var list<string> */
    private array $stagedFiles = [];

    /** @var list<string> */
    private array $stagedRetainedFiles = [];

    /** @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public function locked(int $clientId, int $checkInId, Closure $callback): mixed
    {
        try {
            return Cache::lock('check-in-photos:'.$clientId.':'.$checkInId, 120)->block(10, function () use ($callback, $clientId, $checkInId): mixed {
                try {
                    $this->discardUncommitted($clientId, $checkInId);

                    return $callback();
                } catch (Throwable $error) {
                    foreach ($this->rollbackManifests as $path => $original) {
                        if ($original === null) {
                            $this->disk()->delete($path);
                        } else {
                            $this->disk()->put($path, $original);
                        }
                    }
                    $this->deleteUnreferenced($this->stagedFiles);
                    CheckInPhotoCleanup::unreferenced($this->stagedRetainedFiles);
                    throw $error;
                } finally {
                    $this->rollbackManifests = [];
                    $this->stagedFiles = [];
                    $this->stagedRetainedFiles = [];
                }
            });
        } catch (LockTimeoutException) {
            abort(409, 'These photos are being updated. Please refresh and try again.');
        }
    }

    public function disk(): FilesystemAdapter
    {
        return Storage::disk('temporary_checkin_photos');
    }

    public function stageRetainedFile(string $path): void
    {
        $this->stagedRetainedFiles[] = $path;
        DB::afterCommit(function (): void {
            $this->stagedRetainedFiles = [];
        });
    }

    /** @return array<string, mixed>|null */
    public function metadata(int $clientId, int $checkInId, string $pose): ?array
    {
        $path = $this->manifestPath($clientId, $checkInId, $pose);
        if (! $this->disk()->exists($path)) {
            return null;
        }
        try {
            $value = json_decode($this->disk()->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (! is_array($value) || ! is_string($value['file_path'] ?? null) || ! is_string($value['expires_at'] ?? null)
            || ! is_string($value['updated_at'] ?? null) || ! is_string($value['id'] ?? null)
            || ! is_int($value['photo_version'] ?? null)
            || (! is_null($value['coach_viewed_at'] ?? null) && ! is_string($value['coach_viewed_at']))
            || ! in_array($value['mime_type'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)
            || ! str_starts_with($value['file_path'], $clientId.'/'.$checkInId.'/'.$pose.'--')
            || str_contains($value['file_path'], '..') || str_contains($value['file_path'], '\\')
            || str_contains(substr($value['file_path'], strlen($clientId.'/'.$checkInId.'/')), '/')) {
            return null;
        }
        try {
            CarbonImmutable::parse($value['expires_at']);
            CarbonImmutable::parse($value['updated_at']);
            if (isset($value['coach_viewed_at'])) {
                CarbonImmutable::parse($value['coach_viewed_at']);
            }
        } catch (Throwable) {
            return null;
        }

        return $value;
    }

    /** @return array<string, mixed>|null */
    public function available(int $clientId, int $checkInId, string $pose, int $version): ?array
    {
        $metadata = $this->metadata($clientId, $checkInId, $pose);
        if ($metadata === null || $metadata['photo_version'] > $version) {
            return null;
        }
        try {
            if (! CarbonImmutable::parse($metadata['expires_at'])->isFuture()) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return $this->disk()->exists($metadata['file_path']) ? $metadata : null;
    }

    /** @return list<array<string, mixed>> */
    public function photos(DailyCheckIn $checkIn): array
    {
        $photos = [];
        foreach (BodybuildingPoses::keys() as $pose) {
            $metadata = $this->available($checkIn->client_id, $checkIn->id, $pose, $checkIn->photo_version);
            if ($metadata !== null) {
                $photos[] = ['id' => $metadata['id'], 'pose_key' => $pose,
                    'url' => '/api/clients/'.$checkIn->client_id.'/daily-check-ins/'.$checkIn->id.'/posing/photos/'.$pose,
                    'updated_at' => $metadata['updated_at'], 'expires_at' => $metadata['expires_at'],
                    'coach_viewed_at' => $metadata['coach_viewed_at'] ?? null, 'retained' => false];
            }
        }

        return $photos;
    }

    public function store(DailyCheckIn $checkIn, string $pose, UploadedFile $file): void
    {
        $id = (string) Str::uuid();
        $path = $checkIn->client_id.'/'.$checkIn->id.'/'.$pose.'--'.$id.'.'.$file->extension();
        $stored = $this->disk()->putFileAs(dirname($path), $file, basename($path));
        if ($stored === false) {
            throw new RuntimeException('The temporary photo could not be saved.');
        }
        $this->stagedFiles[] = $path;
        $old = $this->metadata($checkIn->client_id, $checkIn->id, $pose);
        $this->write($checkIn->client_id, $checkIn->id, $pose, ['id' => $id, 'file_path' => $path,
            'mime_type' => $file->getMimeType(), 'photo_version' => $checkIn->photo_version + 1,
            'coach_viewed_at' => null,
            'updated_at' => now()->toISOString(), 'expires_at' => now()->addDays(7)->toISOString()]);
        $this->afterCommit($old === null ? [] : [$old['file_path']]);
    }

    public function remove(DailyCheckIn $checkIn, string $pose): void
    {
        $old = $this->metadata($checkIn->client_id, $checkIn->id, $pose);
        abort_if($old === null, 404);
        $this->write($checkIn->client_id, $checkIn->id, $pose, null);
        $this->afterCommit([$old['file_path']]);
    }

    /** @return array<string, mixed> */
    public function viewed(DailyCheckIn $checkIn, string $pose): array
    {
        $metadata = $this->available($checkIn->client_id, $checkIn->id, $pose, $checkIn->photo_version);
        abort_if($metadata === null, 404);
        if (($metadata['coach_viewed_at'] ?? null) === null) {
            $metadata['coach_viewed_at'] = now()->toISOString();
            $this->write($checkIn->client_id, $checkIn->id, $pose, $metadata);
            $this->afterCommit([]);
        }

        return $metadata;
    }

    public function importLegacy(DailyCheckIn $checkIn, ProgressPhoto $photo): void
    {
        $pose = $photo->pose_key;
        if ($pose === null || ! in_array($pose, BodybuildingPoses::keys(), true)
            || ! in_array($photo->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)
            || $this->metadata($checkIn->client_id, $checkIn->id, $pose) !== null) {
            return;
        }
        $source = Storage::disk('checkin_photos');
        if (! $source->exists($photo->file_path)) {
            return;
        }
        $extension = match ($photo->mime_type) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        };
        $id = (string) Str::uuid();
        $path = $checkIn->client_id.'/'.$checkIn->id.'/'.$pose.'--'.$id.'.'.$extension;
        $stream = $source->readStream($photo->file_path);
        if (! is_resource($stream)) {
            throw new RuntimeException('A legacy photo could not be read.');
        }
        try {
            if (! $this->disk()->put($path, $stream)) {
                throw new RuntimeException('A legacy photo could not be moved to temporary storage.');
            }
        } finally {
            fclose($stream);
        }
        $this->stagedFiles[] = $path;
        $this->write($checkIn->client_id, $checkIn->id, $pose, ['id' => $id, 'file_path' => $path,
            'mime_type' => $photo->mime_type, 'photo_version' => $checkIn->photo_version,
            'coach_viewed_at' => null,
            'updated_at' => $photo->updated_at?->toISOString() ?? now()->toISOString(),
            'expires_at' => $photo->expires_at?->toISOString() ?? now()->toISOString()]);
        $this->afterCommit([]);
    }

    /** @param list<int> $checkInIds */
    public function afterCheckInDeletion(int $clientId, array $checkInIds): void
    {
        DB::afterCommit(function () use ($clientId, $checkInIds): void {
            try {
                foreach ($checkInIds as $id) {
                    $this->deleteMissingCheckIn($clientId, $id);
                }
            } catch (Throwable $error) {
                report($error);
                try {
                    DeleteTemporaryCheckInPhotos::dispatch($clientId, $checkInIds)->afterCommit();
                } catch (Throwable $queueError) {
                    report($queueError);
                }
            }
        });
    }

    public function deleteMissingCheckIn(int $clientId, int $checkInId): void
    {
        abort_unless($clientId > 0 && $checkInId > 0, 404);
        $this->locked($clientId, $checkInId, function () use ($clientId, $checkInId): void {
            if (DailyCheckIn::whereKey($checkInId)->exists()) {
                return;
            }
            $paths = $this->disk()->allFiles($clientId.'/'.$checkInId);
            if ($paths !== [] && ! $this->disk()->delete($paths)) {
                throw new RuntimeException('Deleted check-in photos could not be removed.');
            }
        });
    }

    public function prune(bool $dryRun = false): int
    {
        $removed = 0;
        foreach ($this->disk()->allFiles() as $path) {
            if (preg_match('#^(\d+)/(\d+)/([a-z_]+)\.json$#', $path, $parts)
                && in_array($parts[3], BodybuildingPoses::keys(), true)) {
                $clientId = (int) $parts[1];
                $checkInId = (int) $parts[2];
                $pose = $parts[3];
                $prune = function () use ($clientId, $checkInId, $pose, $path, $dryRun): int {
                    if (! $this->disk()->exists($path)) {
                        return 0;
                    }
                    $metadata = $this->metadata($clientId, $checkInId, $pose);
                    $expired = $metadata === null ? $this->disk()->lastModified($path) <= now()->subDays(7)->timestamp
                        : CarbonImmutable::parse($metadata['expires_at'])->lessThanOrEqualTo(now());
                    $missing = ! DailyCheckIn::whereKey($checkInId)->where('client_id', $clientId)->exists();
                    if (! $expired && ! $missing) {
                        return 0;
                    }
                    if (! $dryRun) {
                        $this->disk()->delete($path);
                        if ($metadata !== null) {
                            $this->cleanup([$metadata['file_path']]);
                        }
                    }

                    return 1;
                };
                $removed += $dryRun ? $prune() : $this->locked($clientId, $checkInId, $prune);
            } elseif (preg_match('#^(\d+)/(\d+)/[a-z_]+--[a-f0-9-]+\.(jpg|jpeg|png|webp)$#', $path, $parts)
                && $this->disk()->exists($path) && $this->disk()->lastModified($path) <= now()->subDays(7)->timestamp) {
                if (! $dryRun) {
                    $this->locked((int) $parts[1], (int) $parts[2], fn () => $this->deleteUnreferenced([$path]));
                }
            }
        }

        return $removed;
    }

    /** @param array<string, mixed>|null $metadata */
    private function write(int $clientId, int $checkInId, string $pose, ?array $metadata): void
    {
        $path = $this->manifestPath($clientId, $checkInId, $pose);
        if (! array_key_exists($path, $this->rollbackManifests)) {
            $this->rollbackManifests[$path] = $this->disk()->exists($path) ? $this->disk()->get($path) : null;
        }
        if ($metadata === null) {
            $this->disk()->delete($path);
        } elseif (! $this->disk()->put($path, json_encode($metadata, JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('The temporary photo could not be saved.');
        }
    }

    /** @param list<string> $oldPaths */
    private function afterCommit(array $oldPaths): void
    {
        DB::afterCommit(function () use ($oldPaths): void {
            $this->rollbackManifests = [];
            $this->stagedFiles = [];
            $this->cleanup($oldPaths);
        });
    }

    /** @param list<string> $paths */
    public function cleanup(array $paths): void
    {
        try {
            $this->deleteUnreferenced($paths);
        } catch (Throwable $error) {
            report($error);
            try {
                DeleteTemporaryPoseFiles::dispatch($paths)->afterCommit();
            } catch (Throwable $queueError) {
                report($queueError);
            }
        }
    }

    /** @param list<string> $paths */
    public function deleteUnreferenced(array $paths): void
    {
        foreach ($paths as $path) {
            if (! preg_match('#^(\d+)/(\d+)/([a-z_]+)--[a-f0-9-]+\.(jpg|jpeg|png|webp)$#', $path, $parts)) {
                throw new RuntimeException('Unexpected temporary photo path.');
            }
            $metadata = $this->metadata((int) $parts[1], (int) $parts[2], $parts[3]);
            if (($metadata['file_path'] ?? null) !== $path && ! $this->disk()->delete($path)) {
                throw new RuntimeException('A temporary check-in photo could not be removed.');
            }
        }
    }

    private function manifestPath(int $clientId, int $checkInId, string $pose): string
    {
        abort_unless($clientId > 0 && $checkInId > 0 && in_array($pose, BodybuildingPoses::keys(), true), 404);

        return $clientId.'/'.$checkInId.'/'.$pose.'.json';
    }

    private function discardUncommitted(int $clientId, int $checkInId): void
    {
        $version = DailyCheckIn::whereKey($checkInId)->where('client_id', $clientId)->value('photo_version');
        if ($version === null) {
            return;
        }
        foreach (BodybuildingPoses::keys() as $pose) {
            $metadata = $this->metadata($clientId, $checkInId, $pose);
            if ($metadata !== null && $metadata['photo_version'] > $version) {
                if (! $this->disk()->delete($this->manifestPath($clientId, $checkInId, $pose))) {
                    throw new RuntimeException('An incomplete photo save could not be cleared.');
                }
                $this->cleanup([$metadata['file_path']]);
            }
        }
    }
}
