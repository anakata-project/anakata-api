<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge([
                'email' => Str::lower($this->input('email')),
            ]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'unique:users,email'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ];
    }
}
