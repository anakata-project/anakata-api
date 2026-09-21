<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Enums\ContactType;
use App\Enums\PreferredChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
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
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'language' => ['sometimes', 'string', 'regex:/^[a-zA-Z]{2}$/'],
            'preferred_channel' => ['sometimes', Rule::enum(PreferredChannel::class)],
            'type' => ['sometimes', Rule::enum(ContactType::class)],
        ];
    }
}
