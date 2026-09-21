<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use Illuminate\Foundation\Http\FormRequest;

class AddBookingExtraRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:16'],
            'qty' => ['required', 'integer', 'min:1'],
            'rate_usd' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
