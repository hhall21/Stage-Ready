<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['client_id', 'coach_id', 'phase', 'review_date', 'feedback', 'next_steps'])]
class CoachingReview extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['review_date' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
