<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\AgencyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reason') && is_string($this->input('reason'))) {
            $this->merge(['reason' => trim((string) $this->input('reason'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([AgencyStatus::Approved->value, AgencyStatus::Rejected->value])],
            'reason' => [
                Rule::requiredIf($this->input('decision') === AgencyStatus::Rejected->value),
                'nullable',
                'string',
            ],
        ];
    }
}
