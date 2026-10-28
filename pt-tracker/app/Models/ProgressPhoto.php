<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $coach_viewed_at
 */
#[Fillable(['check_in_id', 'photo_type', 'file_path', 'pose_key', 'mime_type', 'expires_at', 'coach_viewed_at', 'retained_by_coach_id'])]
#[Hidden(['file_path'])]
class ProgressPhoto extends Model
{
    protected static function booted(): void
    {
        static::creating(function (ProgressPhoto $photo): void {
            $photo->expires_at ??= ($photo->updated_at ?? $photo->created_at ?? now())->toImmutable()->addDays(7);
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'coach_viewed_at' => 'immutable_datetime'];
    }

    /** @param Builder<ProgressPhoto> $query
     * @return Builder<ProgressPhoto>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNotNull('coach_viewed_at')->where('expires_at', '>', now());
    }

    /** @param Builder<ProgressPhoto> $query
     * @return Builder<ProgressPhoto>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '<=', now()));
    }

    /** @return BelongsTo<DailyCheckIn, $this> */
    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(DailyCheckIn::class, 'check_in_id');
    }
}
