<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveDailyCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'check_in_date' => ['nullable', 'date_format:Y-m-d'],
            'bodyweight' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'sleep_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'sleep_quality' => ['nullable', 'integer', 'between:1,5'],
            'energy_level' => ['nullable', 'integer', 'between:1,5'],
            'stress_level' => ['nullable', 'integer', 'between:1,5'],
            'steps' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'water_litres' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'hunger_level' => ['nullable', 'integer', 'between:1,5'],
            'appetite_level' => ['nullable', 'integer', 'between:1,5'],
            'digestive_discomfort' => ['nullable', 'integer', 'between:1,5'],
            'digestion_notes' => ['nullable', 'string', 'max:5000'],
            'dietary_difficulties' => ['nullable', 'string', 'max:5000'],
            'client_id' => ['prohibited'],
            'photo_version' => ['prohibited'],
            'submitted_at' => ['prohibited'],
            'nutrition_completed_at' => ['prohibited'],
            'posing_rating' => ['prohibited'],
            'feedback' => ['prohibited'],
            'is_competition_prep' => ['prohibited'],
            'flatness_rating' => ['prohibited'],
            'fullness_rating' => ['prohibited'],
        ];
    }
}
