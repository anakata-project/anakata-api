<?php

declare(strict_types=1);

namespace App\Http\Requests\Engine;

use Illuminate\Foundation\Http\FormRequest;

class StoreMarketingLeadRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'first_name' => ['required', 'string', 'max:120'],
            'consent' => ['required', 'boolean', 'accepted'],
            'version' => ['required', 'string', 'max:120'],
            'session_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
