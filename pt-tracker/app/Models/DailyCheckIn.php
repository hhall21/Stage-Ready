<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_id', 'check_in_date', 'bodyweight', 'sleep_hours', 'sleep_quality', 'energy_level', 'stress_level', 'steps', 'water_litres', 'notes'])]
class DailyCheckIn extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'bodyweight' => 'decimal:2',
            'sleep_hours' => 'decimal:2',
            'sleep_quality' => 'integer',
            'energy_level' => 'integer',
            'stress_level' => 'integer',
            'steps' => 'integer',
            'water_litres' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return HasMany<ProgressPhoto, $this> */
    public function progressPhotos(): HasMany
    {
        return $this->hasMany(ProgressPhoto::class, 'check_in_id');
    }
}
