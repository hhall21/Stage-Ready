<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveNutritionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $rules = [
            'effective_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'client_id' => ['prohibited'],
            'coach_id' => ['prohibited'],
        ];
        foreach (['training_meals', 'rest_meals'] as $day) {
            $rules[$day] = ['required', 'array', 'list', 'min:1', 'max:12'];
            $rules[$day.'.*'] = ['required', 'array:name,time,foods,calories,protein,carbohydrates,fat'];
            $rules[$day.'.*.name'] = ['required', 'string', 'max:100'];
            $rules[$day.'.*.time'] = ['required', 'date_format:H:i'];
            $rules[$day.'.*.foods'] = ['required', 'string', 'max:3000'];
            foreach (['calories', 'protein', 'carbohydrates', 'fat'] as $macro) {
                $rules[$day.'.*.'.$macro] = ['nullable', 'numeric', 'min:0', 'max:10000'];
            }
        }

        return $rules;
    }
}
