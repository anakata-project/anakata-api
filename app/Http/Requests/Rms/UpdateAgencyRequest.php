<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgencyRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'contact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'network' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payment_terms' => ['sometimes', 'nullable', 'string', 'max:255'],
            'commission_pct' => ['sometimes', 'integer', 'min:0', 'max:30'],
        ];
    }
}
