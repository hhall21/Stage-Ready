<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['training_clip_id', 'trainer_id', 'feedback', 'video_timestamp_seconds', 'review_status', 'feedback_date'])]
class ClipFeedback extends Model
{
    protected $table = 'clip_feedback';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'video_timestamp_seconds' => 'integer',
            'feedback_date' => 'datetime',
        ];
    }

    /** @return BelongsTo<TrainingClip, $this> */
    public function trainingClip(): BelongsTo
    {
        return $this->belongsTo(TrainingClip::class, 'training_clip_id');
    }

    /** @return BelongsTo<User, $this> */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }
}
