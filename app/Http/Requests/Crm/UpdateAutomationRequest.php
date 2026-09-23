<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('reason')) {
            $this->merge([
                'reason' => trim((string) $this->input('reason')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
