<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class BindDealRequest extends FormRequest
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
            'booking_id' => ['sometimes', 'nullable', 'integer', 'exists:bookings,id', 'prohibits:group_id'],
            'group_id' => ['sometimes', 'nullable', 'integer', 'exists:groups,id', 'prohibits:booking_id'],
        ];
    }
}
