<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'role_id' => ['sometimes', 'required', 'integer', 'exists:roles,id'],
        ];
    }
}
