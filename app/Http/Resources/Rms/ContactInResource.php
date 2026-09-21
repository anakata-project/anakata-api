<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Booking;
use App\Models\User;
use App\Policies\BookingPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class ContactInResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     display_reference: string|null,
     *     status: string,
     *     segment: string,
     *     main_channel: string,
     *     channel_of_origin: string,
     *     charges_total: int,
     *     contact: array{id: int, name: string},
     *     travel_advisor: bool,
     *     owner: array{id: int, name: string},
     *     can_act: bool
     * }
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['contact', 'owner', 'bookingRequest']);

        $actor = $request->user();
        $canAct = $actor instanceof User
            && app(BookingPolicy::class)->ownsOrMayActOnAny($actor, $this->resource);

        return [
            'id' => $this->id,
            'display_reference' => $this->displayReference(),
            'status' => $this->status->value,
            'segment' => $this->segment()->value,
            'main_channel' => $this->main_channel->value,
            'channel_of_origin' => $this->channel_of_origin->value,
            'charges_total' => $this->chargesTotal(),
            'contact' => [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
            ],
            'travel_advisor' => (bool) $this->bookingRequest?->travel_advisor,
            'owner' => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ],
            'can_act' => $canAct,
        ];
    }
}
