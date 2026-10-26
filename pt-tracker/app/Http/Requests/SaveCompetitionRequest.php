<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SaveCompetitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof User && ($this->user()?->can('manageCompetitions', $client) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $date = $this->input('competition_date');
        $resultRules = is_string($date) && $date < today()->toDateString()
            ? ['nullable', 'string']
            : ['missing'];

        return [
            'competition_name' => ['required', 'string', 'max:200'],
            'competition_date' => ['required', 'date_format:Y-m-d'],
            'location' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:150'],
            'placing' => [...$resultRules, 'max:50'],
            'notes' => [...$resultRules, 'max:5000'],
            'client_id' => ['prohibited'],
            'coach_id' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'placing.missing' => 'Placing can be recorded after the show date.',
            'notes.missing' => 'Show notes can be recorded after the show date.',
        ];
    }
}
