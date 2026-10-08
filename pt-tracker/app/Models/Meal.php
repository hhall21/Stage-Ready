<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_id', 'meal_date', 'meal_type', 'notes'])]
class Meal extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'meal_date' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return HasMany<MealItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MealItem::class, 'meal_id');
    }
}
