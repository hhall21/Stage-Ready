<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workout_id', 'exercise_id', 'exercise_order', 'notes'])]
class WorkoutExercise extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'exercise_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Workout, $this> */
    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class, 'workout_id');
    }

    /** @return BelongsTo<Exercise, $this> */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'exercise_id');
    }

    /** @return HasMany<ExerciseSet, $this> */
    public function sets(): HasMany
    {
        return $this->hasMany(ExerciseSet::class, 'workout_exercise_id')->orderBy('set_number');
    }
}
