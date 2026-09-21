<?php

declare(strict_types=1);

namespace App\Http\Requests\Rms;

use App\Enums\OfferChannel;
use App\Enums\OfferStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOffersRequest extends FormRequest
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
            'status' => ['sometimes', Rule::in([
                ...array_map(fn (OfferStatus $status): string => $status->value, OfferStatus::cases()),
                OfferStatus::DerivedExpired,
            ])],
            'channel' => ['sometimes', Rule::enum(OfferChannel::class)],
            'q' => ['sometimes', 'string', 'max:255'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
