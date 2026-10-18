<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon $reviewed_at */
#[Fillable(['check_in_id', 'coach_id', 'photo_version', 'posing_rating', 'feedback', 'is_competition_prep', 'flatness_rating', 'fullness_rating', 'reviewed_at'])]
class CheckInPosingReview extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'photo_version' => 'integer',
            'posing_rating' => 'integer',
            'is_competition_prep' => 'boolean',
            'flatness_rating' => 'integer',
            'fullness_rating' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DailyCheckIn, $this> */
    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(DailyCheckIn::class, 'check_in_id');
    }

    /** @return BelongsTo<User, $this> */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
