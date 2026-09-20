<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\ClaimKind;
use App\Models\Booking;
use App\Models\CabinClaim;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Services\Config\CurrentConfig;
use App\Support\Bookings\RequestParty;
use App\Support\BusinessHours;
use App\Support\Iso;
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
            'claims',
        ]);

        $actor = $request->user();
        $canAct = $actor instanceof User
            && app(BookingPolicy::class)->ownsOrMayActOnAny($actor, $this->resource);

        $details = $this->bookingRequest;
        $holdClaim = $this->claims
            ->filter(fn (CabinClaim $claim): bool => $claim->kind === ClaimKind::Hold)
            ->sortByDesc('id')
            ->first();

        $expired = $this->holdExpired();
        $expiresAt = $holdClaim?->expires_at;
        $rules = app(CurrentConfig::class)->businessRules();
        $hours = BusinessHours::fromDocument($rules);
        $remaining = ($expiresAt !== null && ! $expired)
            ? $hours->remainingBusinessMinutes(now(), $expiresAt)
            : 0;

        $dueAt = $details?->sla_due_at;
        $remainingSla = $dueAt === null ? 0 : (int) now()->diffInMinutes($dueAt, false);

        return [
            'id' => $this->id,
            'display_reference' => $this->displayReference(),
            'contact' => [
                'name' => $this->contact->name,
                'preferred_channel' => $details?->preferred_channel->value ?? $this->contact->preferred_channel->value,
            ],
            'travel_advisor' => (bool) $details?->travel_advisor,
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
            'hold' => [
                'expires_at' => Iso::utc($expiresAt),
                'rule' => $details?->hold_rule->value ?? '',
                'remaining_business_minutes' => $remaining,
                'expired' => $expired,
            ],
            'sla' => [
                'due_at' => Iso::utc($dueAt) ?? '',
                'remaining_minutes' => $remainingSla,
                'breached' => $remainingSla < 0,
            ],
            'can_act' => $canAct,
        ];
    }
}
