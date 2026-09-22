<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Models\Agency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgencyUserRequest extends FormRequest
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
        $agency = $this->route('agency');
        $agencyId = $agency instanceof Agency ? $agency->id : 0;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('agency_users', 'email')->where('agency_id', $agencyId),
            ],
        ];
    }
}
