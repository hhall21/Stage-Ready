<?php

namespace App\Jobs;

use App\Models\ProgressPhoto;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteCheckInPhotos implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @param list<string> $paths */
    public function __construct(public array $paths) {}

    public function backoff(): int
    {
        return 60;
    }

    public function handle(): void
    {
        foreach ($this->paths as $path) {
            // A retried cleanup must never remove a currently referenced image.
            if (! ProgressPhoto::where('file_path', $path)->exists()
                && ! Storage::disk('checkin_photos')->delete($path)) {
                throw new RuntimeException('A private check-in photo could not be removed.');
            }
        }
    }
}
