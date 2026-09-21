<?php

declare(strict_types=1);

namespace App\Http\Requests\Engine;

use App\Models\Guest;
use App\Support\BusinessTime;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCompleteGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nationality = $this->input('nationality');

        if (is_string($nationality) && $nationality !== '') {
            $this->merge([
                'nationality' => strtoupper($nationality),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dob' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'nationality' => ['sometimes', 'nullable', 'string', Rule::in(Countries::codes())],
            'ecuador_resident' => ['sometimes', 'boolean'],
            'passport_no' => ['sometimes', 'nullable', 'string'],
            'passport_expiry' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'email' => ['sometimes', 'nullable', 'email'],
            'insurance_declared' => ['sometimes', 'boolean'],
            'guardian_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'guardian_relationship' => ['sometimes', 'nullable', 'string', 'max:255'],
            'guardian_consented' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $today = BusinessTime::now()->toDateString();
            $existing = $this->route('guest');
            $existingGuest = $existing instanceof Guest ? $existing : Guest::query()->find($existing);
            $existingDob = $existingGuest instanceof Guest ? $existingGuest->dob?->toDateString() : null;
            $existingExpiry = $existingGuest instanceof Guest ? $existingGuest->passport_expiry?->toDateString() : null;

            $dob = $this->has('dob') ? $this->input('dob') : $existingDob;
            $expiry = $this->has('passport_expiry') ? $this->input('passport_expiry') : $existingExpiry;

            if (is_string($dob) && $dob !== '' && $dob > $today) {
                $validator->errors()->add('dob', 'Date of birth is in the future.');
            }

            if (is_string($dob) && $dob !== '' && is_string($expiry) && $expiry !== '' && $expiry < $dob) {
                $validator->errors()->add('passport_expiry', 'Passport expiry cannot be before the date of birth.');
            }
        });
    }
}
