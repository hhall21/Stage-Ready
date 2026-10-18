<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavePosePhotoRequest extends FormRequest
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
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }
}
