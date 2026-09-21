<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingFeesRequest extends FormRequest
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
            'png_collected' => ['sometimes', 'boolean'],
            'tct_collected' => ['sometimes', 'boolean'],
        ];
    }
}
