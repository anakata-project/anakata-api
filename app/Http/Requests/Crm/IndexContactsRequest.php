<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use App\Enums\ChannelOfOrigin;
use App\Enums\ContactConsentFilter;
use App\Enums\ContactLifecycle;
use App\Enums\ContactType;
use App\Enums\MainChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexContactsRequest extends FormRequest
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
            'type' => ['sometimes', Rule::enum(ContactType::class)],
            'lifecycle' => ['sometimes', Rule::enum(ContactLifecycle::class)],
            'main_channel' => ['sometimes', Rule::enum(MainChannel::class)],
            'channel_of_origin' => ['sometimes', Rule::enum(ChannelOfOrigin::class)],
            'consent' => ['sometimes', Rule::enum(ContactConsentFilter::class)],
            'q' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
