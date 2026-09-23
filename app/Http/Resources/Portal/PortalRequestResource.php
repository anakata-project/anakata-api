<?php

declare(strict_types=1);

namespace App\Http\Resources\Portal;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\Agencies\PortalPreview;
use App\Support\Portal\PortalRequestWords;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class PortalRequestResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     reference: string|null,
     *     status: BookingStatus,
     *     lead_guest: string,
     *     next: string
     * }
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['contact', 'guests']);

        return [
            'reference' => $this->displayReference(),
            'status' => $this->bookingStatus(),
            'lead_guest' => PortalPreview::leadGuestName($this->resource),
            'next' => PortalRequestWords::forBooking($this->resource),
        ];
    }

    private function bookingStatus(): BookingStatus
    {
        return $this->status;
    }
}
