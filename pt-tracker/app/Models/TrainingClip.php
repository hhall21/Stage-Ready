<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_id', 'exercise_set_id', 'file_path', 'client_notes', 'uploaded_at'])]
class TrainingClip extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<ExerciseSet, $this> */
    public function exerciseSet(): BelongsTo
    {
        return $this->belongsTo(ExerciseSet::class, 'exercise_set_id');
    }

    /** @return HasMany<ClipFeedback, $this> */
    public function feedback(): HasMany
    {
        return $this->hasMany(ClipFeedback::class, 'training_clip_id');
    }
}
