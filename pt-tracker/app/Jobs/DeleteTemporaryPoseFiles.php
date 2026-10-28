<?php

namespace App\Jobs;

use App\Services\TemporaryPosePhotos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteTemporaryPoseFiles implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @param list<string> $paths */
    public function __construct(public array $paths) {}

    public function backoff(): int
    {
        return 60;
    }

    public function handle(TemporaryPosePhotos $photos): void
    {
        $photos->deleteUnreferenced($this->paths);
    }
}
