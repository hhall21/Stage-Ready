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
            'reading_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'systolic' => ['nullable', 'integer', 'between:1,400', ...($this->filled('diastolic') ? ['gt:diastolic'] : [])],
            'diastolic' => ['nullable', 'integer', 'between:1,300'],
            'resting_heart_rate' => ['nullable', 'integer', 'between:1,300'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'client_id' => ['prohibited'],
        ];
    }
}
