<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\OverdueDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OverdueDecisionRequest extends FormRequest
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
            'decision' => ['required', Rule::enum(OverdueDecision::class)],
            'reason' => ['required', 'string'],
            'new_due_date' => [
                Rule::requiredIf($this->input('decision') === OverdueDecision::Extend->value),
                'nullable',
                'date_format:Y-m-d',
            ],
        ];
    }
}
