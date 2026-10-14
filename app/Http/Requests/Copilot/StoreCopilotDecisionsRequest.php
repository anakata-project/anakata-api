<?php

declare(strict_types=1);

namespace App\Http\Requests\Copilot;

use Illuminate\Foundation\Http\FormRequest;

class StoreCopilotDecisionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*' => ['required', 'array'],
            'decisions.*.action' => ['required', 'string', 'in:approve,reject'],
            'decisions.*.result' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
