<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use Illuminate\Foundation\Http\FormRequest;

class MarkWireReceivedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('bank_reference') && is_string($this->input('bank_reference'))) {
            $this->merge(['bank_reference' => trim((string) $this->input('bank_reference'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'bank_reference' => ['required', 'string'],
        ];
    }
}
