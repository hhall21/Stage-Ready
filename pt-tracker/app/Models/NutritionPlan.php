<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['client_id', 'coach_id', 'effective_from', 'training_meals', 'rest_meals', 'notes'])]
class NutritionPlan extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['effective_from' => 'date', 'training_meals' => 'array', 'rest_meals' => 'array', 'cancelled_at' => 'datetime'];
    }
}
