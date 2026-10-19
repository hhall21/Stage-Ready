<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** @property-read Carbon $workout_date */
#[Fillable(['client_id', 'trainer_id', 'workout_name', 'workout_date', 'notes', 'completed', 'mesocycle_id', 'template_slot', 'training_split_id', 'split_day_number'])]
class Workout extends Model
{
    public const MAX_EXERCISES = 10;

    /** @param Builder<Workout> $query
     * @return Builder<Workout>
     */
    public function scopeUnlogged(Builder $query): Builder
    {
        return $query->where('completed', false)->whereNull('logged_at')
            ->whereDoesntHave('workoutExercises.sets', fn ($sets) => $sets->where('completed', true))
            ->whereDoesntHave('workoutExercises.sets.trainingClips');
    }

    public function hasPerformance(): bool
    {
        return $this->completed || $this->getAttribute('logged_at') !== null
            || $this->workoutExercises()->whereHas('sets', fn ($sets) => $sets->where('completed', true)->orWhereHas('trainingClips'))->exists();
    }

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
