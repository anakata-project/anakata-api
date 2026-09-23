<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Enums\CabinCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePortalRequestRequest extends FormRequest
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
            'departure_id' => ['required', 'integer', 'exists:departures,id'],
            'category' => ['required', Rule::enum(CabinCategory::class)],
            'cabins' => ['required', 'array', 'min:1'],
            'cabins.*.adults' => ['required', 'integer', 'min:1'],
            'cabins.*.children' => ['required', 'integer', 'min:0'],
            'client' => ['required', 'array'],
            'client.name' => ['required', 'string', 'max:255'],
            'client.email' => ['required', 'email', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'client_of_record' => ['accepted'],
            'price' => ['prohibited'],
            'discount' => ['prohibited'],
            'commission_pct' => ['prohibited'],
            'promo_code' => ['prohibited'],
            'agency_id' => ['prohibited'],
        ];
    }
}
