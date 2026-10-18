<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['client_id', 'coach_id', 'effective_from', 'show_date', 'days', 'notes'])]
class PracticePlan extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['effective_from' => 'date', 'show_date' => 'date', 'days' => 'array', 'cancelled_at' => 'datetime'];
    }
}
