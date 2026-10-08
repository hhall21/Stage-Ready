<?php

namespace App\Http\Requests;

use App\Models\Workout;
use Illuminate\Foundation\Http\FormRequest;

class SaveMesocycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'templates' => ['required', 'array', 'min:1', 'max:14'],
            'templates.*' => ['required', 'array:name,weekday,notes,exercises'],
            'templates.*.name' => ['required', 'string', 'max:150'],
            'templates.*.weekday' => ['required', 'integer', 'between:1,7'],
            'templates.*.notes' => ['nullable', 'string', 'max:5000'],
            'templates.*.exercises' => ['required', 'array', 'min:1', 'max:'.Workout::MAX_EXERCISES],
            'templates.*.exercises.*' => ['required', 'array:exercise_id,notes,sets'],
            'templates.*.exercises.*.exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'templates.*.exercises.*.notes' => ['nullable', 'string', 'max:5000'],
            'templates.*.exercises.*.sets' => ['required', 'array', 'min:1', 'max:100'],
            'templates.*.exercises.*.sets.*' => ['required', 'array:reps,weight,rir'],
            'templates.*.exercises.*.sets.*.reps' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'templates.*.exercises.*.sets.*.weight' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'templates.*.exercises.*.sets.*.rir' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'client_id' => ['prohibited'], 'trainer_id' => ['prohibited'],
        ];
    }
}
