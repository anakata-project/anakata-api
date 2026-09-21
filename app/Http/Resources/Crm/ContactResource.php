<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Models\Contact;
use App\Support\Crm\ContactConsentSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contact
 */
class ContactResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string|null,
     *     phone: string|null,
     *     phone_e164: string|null,
     *     country: string|null,
     *     language: string,
     *     preferred_channel: string,
     *     type: string,
     *     first_touch: array<string, mixed>|null,
     *     last_touch: array<string, mixed>|null,
     *     lifetime_value: int,
     *     segment: string,
     *     lifecycle: string,
     *     nps: null,
     *     consent: array{marketing: bool, transactional: true},
     *     main_channel: string|null,
     *     channel_of_origin: string|null,
     *     resolved_from_alias: bool,
     *     alias_id: int|null,
     *     merge_id: int|null,
     *     bookings?: list<array<string, mixed>>
     * }
     */
    public function toArray(Request $request): array
    {
        $consent = ContactConsentSummary::for($this->resource);

        $payload = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_e164' => $this->phone_e164,
            'country' => $this->country,
            'language' => $this->language,
            'preferred_channel' => $this->preferred_channel->value,
            'type' => $this->type->value,
            'first_touch' => $this->first_touch,
            'last_touch' => $this->last_touch,
            'lifetime_value' => (int) $this->getAttribute('lifetime_value'),
            'segment' => (string) $this->getAttribute('segment'),
            'lifecycle' => (string) $this->getAttribute('lifecycle'),
            'nps' => null,
            'consent' => $consent,
            'main_channel' => $this->nullableString($this->getAttribute('first_main_channel')),
            'channel_of_origin' => $this->nullableString($this->getAttribute('first_channel_of_origin')),
            'resolved_from_alias' => $this->resource->resolvedFromAliasId !== null,
            'alias_id' => $this->resource->resolvedFromAliasId,
            'merge_id' => $this->resource->resolvedMergeId,
        ];

        if ($this->relationLoaded('bookings')) {
            $payload['bookings'] = ContactBookingResource::collection($this->bookings)->resolve();
        }

        return $payload;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
