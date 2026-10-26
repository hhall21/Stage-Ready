<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property array<string, mixed> $snapshot
 * @property Carbon $started_on
 * @property Carbon $ended_at
 */
#[Fillable(['client_id', 'coach_id', 'started_on', 'ended_at', 'snapshot', 'pdf_base64'])]
#[Hidden(['snapshot', 'pdf_base64'])]
class CoachingHandover extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['snapshot' => 'array', 'started_on' => 'date', 'ended_at' => 'datetime'];
    }
}
