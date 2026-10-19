<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $client_id
 * @property int|null $trainer_id
 * @property string $name
 * @property array<int, array<string, mixed>> $days
 */
#[Fillable(['client_id', 'trainer_id', 'name', 'start_date', 'days', 'notes'])]
class TrainingSplit extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['start_date' => 'date', 'days' => 'array'];
    }

    /** @return HasMany<Workout, $this> */
    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    /** @return HasMany<SplitDaySelection, $this> */
    public function selections(): HasMany
    {
        return $this->hasMany(SplitDaySelection::class);
    }
}
