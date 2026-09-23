<?php

declare(strict_types=1);

namespace App\Http\Resources\Portal;

use App\Models\Booking;
use App\Support\Agencies\PortalPreview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class PortalBookingResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     reference: string|null,
     *     departure_date: string,
     *     itinerary: string,
     *     status: string,
     *     lead_guest: string,
     *     net_due: int,
     *     payment_state: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'departure_date' => $this->departure->date->toDateString(),
            'itinerary' => $this->departure->itinerary->code,
            'status' => $this->status->value,
            'lead_guest' => PortalPreview::leadGuestName($this->resource),
            'net_due' => PortalPreview::netDue($this->resource),
            'payment_state' => $this->resource->paymentStateWords(),
        ];
    }
}
