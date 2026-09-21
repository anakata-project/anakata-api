<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingBillingRequest extends FormRequest
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
            'billing_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billing_address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'billing_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'billing_phone' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
