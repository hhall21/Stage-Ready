<?php

namespace App\Http\Requests;

use App\Models\Workout;
use Illuminate\Foundation\Http\FormRequest;

class SaveTrainingSplitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'days' => ['required', 'array', 'list', 'min:1', 'max:15'],
            'days.*' => ['required', 'array:name,is_rest_day,notes,exercises'],
            'days.*.is_rest_day' => ['required', 'boolean'],
            'days.*.name' => ['nullable', 'string', 'max:150'],
            'days.*.notes' => ['nullable', 'string', 'max:5000'],
            'days.*.exercises' => ['present', 'array', 'list', 'max:'.Workout::MAX_EXERCISES],
            'days.*.exercises.*' => ['required', 'array:exercise_id,notes,sets'],
            'days.*.exercises.*.exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'days.*.exercises.*.notes' => ['nullable', 'string', 'max:5000'],
            'days.*.exercises.*.sets' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'days.*.exercises.*.sets.*' => ['required', 'array:reps,weight,rir'],
            'days.*.exercises.*.sets.*.reps' => ['nullable', 'integer', 'between:0,4294967295'],
            'days.*.exercises.*.sets.*.weight' => ['nullable', 'numeric', 'between:0,99999.99'],
            'days.*.exercises.*.sets.*.rir' => ['nullable', 'numeric', 'between:0,99.9'],
            'client_id' => ['prohibited'], 'trainer_id' => ['prohibited'],
        ];
        $days = $this->input('days');
        if (is_array($days) && array_is_list($days) && count($days) <= 15) {
            foreach ($days as $index => $day) {
                if (! is_array($day)) {
                    continue;
                }
                if (in_array($day['is_rest_day'] ?? null, [false, 0, '0'], true)) {
                    $rules['days.'.$index.'.name'] = ['required', 'string', 'max:150'];
                    $rules['days.'.$index.'.exercises'] = ['required', 'array', 'list', 'min:1', 'max:'.Workout::MAX_EXERCISES];
                } elseif (in_array($day['is_rest_day'] ?? null, [true, 1, '1'], true)) {
                    $rules['days.'.$index.'.exercises'] = ['present', 'array', 'size:0'];
                }
            }
        }

        return $rules;
    }
}
