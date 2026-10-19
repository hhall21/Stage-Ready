<?php

namespace App\Http\Requests;

use App\Models\Workout;
use Illuminate\Foundation\Http\FormRequest;

class SaveWorkoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        if ($this->user()?->role === 'client' && ! $this->isMethod('POST')) {
            return [
                'completed' => ['required', 'boolean'],
                'workout_name' => ['prohibited'], 'workout_date' => ['prohibited'],
                'exercises' => ['prohibited'], 'notes' => ['prohibited'],
                'client_id' => ['prohibited'], 'trainer_id' => ['prohibited'],
                'training_split_id' => ['prohibited'], 'split_day_number' => ['prohibited'],
            ];
        }
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'workout_name' => [$presence, 'string', 'max:150'],
            'workout_date' => [$presence, 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'completed' => ['sometimes', 'boolean'],
            'client_id' => ['prohibited'],
            'trainer_id' => ['prohibited'],
            'training_split_id' => ['prohibited'], 'split_day_number' => ['prohibited'],
            'exercises' => ['sometimes', 'array', 'max:'.Workout::MAX_EXERCISES],
            'exercises.*' => ['required', 'array:exercise_id,notes,sets'],
            'exercises.*.exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'exercises.*.notes' => ['nullable', 'string', 'max:5000'],
            'exercises.*.sets' => ['sometimes', 'array', 'max:100'],
            'exercises.*.sets.*' => ['required', 'array:reps,weight,rir,completed,notes'],
            'exercises.*.sets.*.reps' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'exercises.*.sets.*.weight' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'exercises.*.sets.*.rir' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'exercises.*.sets.*.completed' => ['sometimes', 'boolean'],
            'exercises.*.sets.*.notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
