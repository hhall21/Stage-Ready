<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePosingReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'photo_version' => ['required', 'integer', 'min:0'],
            'posing_rating' => ['required', 'integer', 'between:1,10'],
            'feedback' => ['required', 'string', 'max:10000'],
            'is_competition_prep' => ['required', 'boolean'],
            'flatness_rating' => ['nullable', 'prohibited_if:is_competition_prep,false', 'integer', 'between:1,10'],
            'fullness_rating' => ['nullable', 'prohibited_if:is_competition_prep,false', 'integer', 'between:1,10'],
        ];
    }
}
