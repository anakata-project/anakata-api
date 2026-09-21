<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Models\Booking;
use App\Models\User;
use App\Policies\BookingPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class ContactBookingResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     display_reference: string|null,
     *     departure_date: string,
     *     status: string,
     *     charges_total: int,
     *     balance: int,
     *     can_act: bool
     * }
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        $canAct = $actor instanceof User
            && app(BookingPolicy::class)->ownsOrMayActOnAny($actor, $this->resource);

        return [
            'id' => $this->id,
            'display_reference' => $this->displayReference(),
            'departure_date' => $this->departure->date->toDateString(),
            'status' => $this->status->value,
            'charges_total' => $this->chargesTotal(),
            'balance' => $this->balance(),
            'can_act' => $canAct,
        ];
    }
}
