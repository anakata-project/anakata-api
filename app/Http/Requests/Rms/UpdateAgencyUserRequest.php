<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\AgencyUserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgencyUserRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::in([
                AgencyUserStatus::Active->value,
                AgencyUserStatus::Disabled->value,
            ])],
        ];
    }
}
