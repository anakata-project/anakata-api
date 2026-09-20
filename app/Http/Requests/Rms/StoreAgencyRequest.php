<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgencyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'contact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'network' => ['sometimes', 'nullable', 'string', 'max:255'],
            'commission_pct' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:30'],
            'payment_terms' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
