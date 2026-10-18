<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePracticePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'effective_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'show_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'days' => ['required', 'array', 'list', 'size:7'],
            'days.*' => ['required', 'array:weekday,cardio_mode,cardio_minutes,cardio_intensity,posing_rounds,pose_hold_seconds,vacuum_sets,vacuum_hold_seconds,notes'],
            'days.*.weekday' => ['required', 'integer', 'between:1,7', 'distinct'],
            'days.*.cardio_mode' => ['required', 'string', 'max:150'],
            'days.*.cardio_minutes' => ['required', 'integer', 'between:0,300'],
            'days.*.cardio_intensity' => ['nullable', 'string', 'max:250'],
            'days.*.posing_rounds' => ['required', 'integer', 'between:0,100'],
            'days.*.pose_hold_seconds' => ['required', 'integer', 'between:1,300'],
            'days.*.vacuum_sets' => ['required', 'integer', 'between:0,100'],
            'days.*.vacuum_hold_seconds' => ['required', 'integer', 'between:1,300'],
            'days.*.notes' => ['nullable', 'string', 'max:2000'],
            'client_id' => ['prohibited'],
            'coach_id' => ['prohibited'],
        ];
    }
}
