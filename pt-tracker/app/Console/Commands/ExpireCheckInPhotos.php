<?php

namespace App\Console\Commands;

use App\Models\DailyCheckIn;
use App\Models\ProgressPhoto;
use App\Models\User;
use App\Services\TemporaryPosePhotos;
use App\Support\CheckInPhotoCleanup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExpireCheckInPhotos extends Command
{
    protected $signature = 'photos:expire {--dry-run : Count expired photos without changing records or files}';

    protected $description = 'Remove check-in photo records and private files after seven days, preserving measurements and reviews';

    public function handle(TemporaryPosePhotos $temporary): int
    {
        $query = ProgressPhoto::where(fn ($query) => $query->expired()->orWhere(fn ($query) => $query
            ->whereNotNull('pose_key')->whereNull('coach_viewed_at')));
        if ($this->option('dry-run')) {
            $this->info($query->count().' expired or legacy database photo(s); '.$temporary->prune(true).' expired temporary photo(s). No records or files changed.');

            return self::SUCCESS;
        }
        $removed = 0;
        $failed = 0;
        $query->chunkById(100, function ($photos) use ($temporary, &$removed, &$failed): void {
            foreach ($photos as $candidate) {
                try {
                    $checkIn = DailyCheckIn::find($candidate->check_in_id);
                    if ($checkIn === null) {
                        continue;
                    }
                    $removed += (int) $temporary->locked($checkIn->client_id, $checkIn->id, fn () => DB::transaction(function () use ($candidate, $checkIn, $temporary): bool {
                        // Match uploads' lock order, then recheck expiry in case a photo was replaced.
                        User::whereKey($checkIn->client_id)->lockForUpdate()->firstOrFail();
                        $lockedCheckIn = DailyCheckIn::whereKey($checkIn->id)->lockForUpdate()->firstOrFail();
                        $photo = ProgressPhoto::whereKey($candidate->id)->lockForUpdate()->first();
                        if ($photo === null) {
                            return false;
                        }
                        if ($photo->expires_at?->isFuture()) {
                            if ($photo->pose_key === null || $photo->coach_viewed_at !== null) {
                                return false;
                            }
                            $temporary->importLegacy($lockedCheckIn, $photo);
                        }
                        $path = $photo->file_path;
                        $photo->delete();
                        CheckInPhotoCleanup::afterCommit([$path]);

                        return true;
                    }));
                } catch (Throwable $error) {
                    report($error);
                    $failed++;
                    $this->warn('Photo cleanup deferred for record '.$candidate->id.'.');
                }
            }
        });
        try {
            $temporaryCount = $temporary->prune();
            $this->info('Processed '.$removed.' database photo record(s); removed '.$temporaryCount.' temporary photo(s); deferred '.$failed.'.');
        } catch (Throwable $error) {
            report($error);
            $failed++;
            $this->warn('Temporary photo cleanup deferred.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
