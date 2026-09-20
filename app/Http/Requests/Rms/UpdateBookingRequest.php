<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingRequest extends FormRequest
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
            'internal_notes' => ['sometimes', 'nullable', 'string'],
            'owner_id' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }
}
