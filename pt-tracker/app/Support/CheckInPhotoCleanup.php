<?php

namespace App\Support;

use App\Jobs\DeleteCheckInPhotos;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckInPhotoCleanup
{
    /** @param list<string> $paths */
    public static function afterCommit(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        DB::afterCommit(function () use ($paths): void {
            try {
                (new DeleteCheckInPhotos($paths))->handle();
            } catch (Throwable $exception) {
                report($exception);
                self::retry($paths);
            }
        });
    }

    /** @param list<string> $paths */
    public static function unreferenced(array $paths): void
    {
        try {
            (new DeleteCheckInPhotos($paths))->handle();
        } catch (Throwable $exception) {
            report($exception);
            self::retry($paths);
        }
    }

    /** @param list<string> $paths */
    private static function retry(array $paths): void
    {
        try {
            DeleteCheckInPhotos::dispatch($paths)->afterCommit();
        } catch (Throwable $exception) {
            // Storage or queue failures must not turn a committed save into an error.
            report($exception);
        }
    }
}
