<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('utm_campaign') && is_string($this->input('utm_campaign'))) {
            $trimmed = strtolower(trim($this->string('utm_campaign')->toString()));
            $this->merge([
                'utm_campaign' => $trimmed === '' ? null : $trimmed,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'offer_id' => ['nullable', 'integer', 'exists:offers,id'],
            'utm_campaign' => ['nullable', 'string', 'max:120', Rule::unique('campaigns', 'utm_campaign')],
            'audience' => ['nullable', 'string', 'max:500'],
            'media_spend' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
