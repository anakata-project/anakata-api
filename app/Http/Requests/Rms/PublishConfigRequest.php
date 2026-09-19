<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use Illuminate\Foundation\Http\FormRequest;

class PublishConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'document' => ['required', 'array'],
            'base_version' => ['required', 'integer', 'min:0'],
            'approval_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
