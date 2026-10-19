<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class SafePassword implements ValidationRule
{
    /** @param Closure(string): PotentiallyTranslatedString $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        // Bcrypt only uses the first 72 bytes, including for multibyte text.
        if (strlen($value) > 72) {
            $fail('The :attribute must not exceed 72 bytes.');
        }
        if (str_contains($value, "\0")) {
            $fail('The :attribute must not contain null characters.');
        }
    }
}
