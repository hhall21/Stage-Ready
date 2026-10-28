<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteTrainingClipFiles implements ShouldQueue
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
            if (! DB::table('training_clips')->where('file_path', $path)->exists()
                && ! Storage::disk('local')->delete($path)) {
                throw new RuntimeException('A private training clip could not be removed.');
            }
        }
    }
}
