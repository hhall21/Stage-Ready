<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['client_id', 'reading_date', 'systolic', 'diastolic', 'resting_heart_rate', 'notes'])]
class WeeklyHealthReading extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reading_date' => 'date',
            'systolic' => 'integer',
            'diastolic' => 'integer',
            'resting_heart_rate' => 'integer',
        ];
    }
}
