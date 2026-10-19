<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['calendar_date', 'day_number'])]
class SplitDaySelection extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['calendar_date' => 'date', 'day_number' => 'integer'];
    }
}
