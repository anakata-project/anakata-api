<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Booking;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Support\Bookings\RequestParty;
use App\Support\Bookings\RequestSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingRequestResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     display_reference: string|null,
     *     contact: array{name: string, preferred_channel: string},
     *     travel_advisor: bool,
     *     party: string,
     *     departure: array{id: int, date: string, yacht: array{id: int, code: string, name: string}},
     *     cabin_label: string,
     *     estimated_value: int,
     *     hold: array{expires_at: string|null, rule: string, remaining_business_minutes: int, expired: bool},
     *     sla: array{due_at: string, remaining_minutes: int, breached: bool},
     *     can_act: bool
     * }
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'departure.yacht',
            'cabin',
            'contact',
            'bookingRequest',
            'activeClaims',
        ]);

        $actor = $request->user();
        $canAct = $actor instanceof User
            && app(BookingPolicy::class)->ownsOrMayActOnAny($actor, $this->resource);

        $summary = RequestSummary::for($this->resource);
        $hold = $summary['hold'] ?? [
            'expires_at' => null,
            'expired' => $this->holdExpired(),
            'rule' => '',
            'remaining_business_minutes' => 0,
        ];
        $sla = $summary['sla'] ?? [
            'due_at' => '',
            'remaining_minutes' => 0,
            'breached' => false,
        ];

        return [
            'id' => $this->id,
            'display_reference' => $this->displayReference(),
            'contact' => [
                'name' => $this->contact->name,
                'preferred_channel' => $summary['preferred_channel'] ?? $this->contact->preferred_channel->value,
            ],
            'travel_advisor' => (bool) ($summary['travel_advisor'] ?? false),
            'party' => RequestParty::label($this->adults, $this->children),
            'departure' => [
                'id' => $this->departure->id,
                'date' => $this->departure->date->toDateString(),
                'yacht' => [
                    'id' => $this->departure->yacht->id,
                    'code' => $this->departure->yacht->code,
                    'name' => $this->departure->yacht->name,
                ],
            ],
            'cabin_label' => $this->cabinLabel(),
            'estimated_value' => $this->total,
            'hold' => $hold,
            'sla' => $sla,
            'can_act' => $canAct,
        ];
    }
}
