<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
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
        $campaign = $this->route('campaign');
        $id = is_object($campaign) ? $campaign->id : null;

        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'offer_id' => ['sometimes', 'nullable', 'integer', 'exists:offers,id'],
            'utm_campaign' => ['sometimes', 'nullable', 'string', 'max:120', Rule::unique('campaigns', 'utm_campaign')->ignore($id)],
            'audience' => ['sometimes', 'nullable', 'string', 'max:500'],
            'media_spend' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
