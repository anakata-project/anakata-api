<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Offer;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Offer
 */
class OfferResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     reference: string,
     *     code: string,
     *     name: string,
     *     type: string,
     *     value: int|null,
     *     value_text: string|null,
     *     channel: string,
     *     partner: string|null,
     *     cabin_types: list<string>,
     *     itinerary_codes: list<string>,
     *     booking_from: string|null,
     *     booking_to: string|null,
     *     travel_from: string|null,
     *     travel_to: string|null,
     *     combinable: bool,
     *     is_promo_code: bool,
     *     badge: string|null,
     *     show_on_card: bool,
     *     show_on_departures: bool,
     *     price_line: string|null,
     *     terms: string|null,
     *     status: string,
     *     stored_status: string,
     *     approved_by: array{id: int, name: string}|null,
     *     approved_at: string|null,
     *     approval_reason: string|null,
     *     benefit_label: string,
     *     scope_label: string,
     *     booking_window_label: string,
     *     travel_window_label: string,
     *     engine_placement: string,
     *     live_departures_count: int
     * }
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('approvedBy');

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'value' => $this->value,
            'value_text' => $this->value_text,
            'channel' => $this->channel->value,
            'partner' => $this->partner,
            'cabin_types' => $this->cabin_types,
            'itinerary_codes' => $this->itinerary_codes,
            'booking_from' => $this->booking_from?->toDateString(),
            'booking_to' => $this->booking_to?->toDateString(),
            'travel_from' => $this->travel_from?->toDateString(),
            'travel_to' => $this->travel_to?->toDateString(),
            'combinable' => $this->combinable,
            'is_promo_code' => $this->is_promo_code,
            'badge' => $this->badge,
            'show_on_card' => $this->show_on_card,
            'show_on_departures' => $this->show_on_departures,
            'price_line' => $this->price_line,
            'terms' => $this->terms,
            'status' => $this->derivedStatus(),
            'stored_status' => $this->status->value,
            'approved_by' => $this->approvedBy === null ? null : [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
            ],
            'approved_at' => $this->approved_at !== null ? Iso::utc($this->approved_at) : null,
            'approval_reason' => $this->approval_reason,
            'benefit_label' => $this->benefitLabel(),
            'scope_label' => $this->scopeLabel(),
            'booking_window_label' => $this->bookingWindowLabel(),
            'travel_window_label' => $this->travelWindowLabel(),
            'engine_placement' => $this->enginePlacement(),
            'live_departures_count' => $this->liveDeparturesCount(),
        ];
    }
}
