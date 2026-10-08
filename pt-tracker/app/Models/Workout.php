<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_id', 'trainer_id', 'workout_name', 'workout_date', 'notes', 'completed', 'mesocycle_id', 'template_slot'])]
class Workout extends Model
{
    public const MAX_EXERCISES = 10;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'workout_date' => 'date',
            'completed' => 'boolean',
            'logged_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<Mesocycle, $this> */
    public function mesocycle(): BelongsTo
    {
        return $this->belongsTo(Mesocycle::class);
    }

    /** @return BelongsTo<User, $this> */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    /** @return HasMany<WorkoutExercise, $this> */
    public function workoutExercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class, 'workout_id')->orderBy('exercise_order');
    }
}
