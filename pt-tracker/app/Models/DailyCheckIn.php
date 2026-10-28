<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $check_in_date
 * @property Carbon|null $submitted_at
 */
#[Fillable(['client_id', 'check_in_date', 'bodyweight', 'sleep_hours', 'sleep_quality', 'energy_level', 'stress_level', 'steps', 'water_litres', 'notes', 'hunger_level', 'appetite_level', 'digestive_discomfort', 'digestion_notes', 'dietary_difficulties'])]
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
            'hunger_level' => 'integer',
            'appetite_level' => 'integer',
            'digestive_discomfort' => 'integer',
            'nutrition_completed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'photo_version' => 'integer',
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

    /** @return HasMany<ProgressPhoto, $this> */
    public function posePhotos(): HasMany
    {
        return $this->progressPhotos()->whereNotNull('pose_key');
    }

    /** @return HasMany<ProgressPhoto, $this> */
    public function retainedPosePhotos(): HasMany
    {
        return $this->posePhotos()->available();
    }

    /** @return HasMany<CheckInPosingReview, $this> */
    public function posingReviews(): HasMany
    {
        return $this->hasMany(CheckInPosingReview::class, 'check_in_id');
    }
}
