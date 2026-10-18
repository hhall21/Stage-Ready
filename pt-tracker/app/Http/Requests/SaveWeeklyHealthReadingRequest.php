<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveWeeklyHealthReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'reading_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'systolic' => ['required', 'integer', 'between:1,400', 'gt:diastolic'],
            'diastolic' => ['required', 'integer', 'between:1,300'],
            'resting_heart_rate' => ['required', 'integer', 'between:1,300'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'client_id' => ['prohibited'],
        ];
    }
}
