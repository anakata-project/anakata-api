<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class PreviewTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact_id' => ['required_without:booking_id', 'integer', 'exists:contacts,id'],
            'booking_id' => ['required_without:contact_id', 'integer', 'exists:bookings,id'],
            'version' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
