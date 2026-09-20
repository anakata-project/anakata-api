<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\RefundRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideRefundRequest extends FormRequest
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
            'decision' => ['required', Rule::in([
                RefundRequestStatus::Approved->value,
                RefundRequestStatus::Rejected->value,
            ])],
            'reason' => ['required', 'string'],
        ];
    }
}
