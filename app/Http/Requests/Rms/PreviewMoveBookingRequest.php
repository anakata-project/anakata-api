<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use Illuminate\Foundation\Http\FormRequest;

class PreviewMoveBookingRequest extends FormRequest
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
            'departure_id' => ['required', 'integer', 'exists:departures,id'],
            'cabin_code' => ['sometimes', 'nullable', 'string', 'max:16'],
        ];
    }
}
