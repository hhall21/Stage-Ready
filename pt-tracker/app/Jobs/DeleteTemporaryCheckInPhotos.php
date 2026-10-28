<?php

namespace App\Jobs;

use App\Services\TemporaryPosePhotos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteTemporaryCheckInPhotos implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @param list<int> $checkInIds */
    public function __construct(public int $clientId, public array $checkInIds) {}

    public function backoff(): int
    {
        return 60;
    }

    public function handle(TemporaryPosePhotos $photos): void
    {
        foreach ($this->checkInIds as $id) {
            $photos->deleteMissingCheckIn($this->clientId, $id);
        }
    }
}
