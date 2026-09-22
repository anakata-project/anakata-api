<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class NoPersonalRecord implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $text = is_string($value) ? $value : '';

        if (preg_match('/\b[A-Z]{0,2}\d{8,9}\b/i', $text) === 1) {
            $fail('Passport numbers belong in the RMS.');
        }

        if (preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $text) === 1
            || preg_match('/\b\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4}\b/', $text) === 1) {
            $fail('Dates of birth belong in the RMS.');
        }
    }
}
