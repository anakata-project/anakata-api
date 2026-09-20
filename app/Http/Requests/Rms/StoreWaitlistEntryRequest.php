<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\CabinCategory;
use App\Enums\PreferredChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWaitlistEntryRequest extends FormRequest
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
            'cabin_category' => ['required', Rule::enum(CabinCategory::class)],
            'client' => ['required', 'array'],
            'client.name' => ['required', 'string', 'max:255'],
            'client.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'client.phone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'client.country' => ['sometimes', 'nullable', 'string', 'size:2'],
            'client.preferred_channel' => ['sometimes', Rule::enum(PreferredChannel::class)],
            'adults' => ['required', 'integer', 'min:1', 'max:36'],
            'children' => ['required', 'integer', 'min:0', 'max:36'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
