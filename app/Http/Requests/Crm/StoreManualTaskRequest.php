<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class StoreManualTaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'due_at' => ['required', 'date'],
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'deal_id' => ['sometimes', 'nullable', 'integer', 'exists:deals,id'],
            'owner_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
