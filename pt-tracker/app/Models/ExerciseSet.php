<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workout_exercise_id', 'set_number', 'reps', 'weight', 'rir', 'completed', 'notes'])]
class ExerciseSet extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'set_number' => 'integer',
            'reps' => 'integer',
            'weight' => 'decimal:2',
            'rir' => 'decimal:1',
            'completed' => 'boolean',
        ];
    }

    /** @return BelongsTo<WorkoutExercise, $this> */
    public function workoutExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutExercise::class, 'workout_exercise_id');
    }

    /** @return HasMany<TrainingClip, $this> */
    public function trainingClips(): HasMany
    {
        return $this->hasMany(TrainingClip::class, 'exercise_set_id');
    }
}
