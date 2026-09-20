<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Booking;
use App\Models\Group;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Support\Bookings\RequestSummary;
use App\Support\Bookings\Transitions;
use App\Support\Iso;
use App\Support\Payments\Ledger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     reference: string|null,
     *     request_reference: string|null,
     *     display_reference: string|null,
     *     type: string,
     *     status: string,
     *     segment: string,
     *     main_channel: string,
     *     channel_of_origin: string,
     *     adults: int,
     *     children: int,
     *     party_label: string,
     *     back_to_back: bool,
     *     total: int,
     *     paid: int,
     *     pledged: int,
     *     balance: int,
     *     payments_count: int,
     *     deposit_pct: int,
     *     deposit_amount: int,
     *     balance_days: int,
     *     balance_due_date: string,
     *     overdue: bool,
     *     overdue_days: int|null,
     *     wire_window_ends_at: string|null,
     *     price_lines: list<array{code: string, label: string, amount: int}>,
     *     rates_version: array{id: int, version: int},
     *     internal_notes: string|null,
     *     can_act: bool,
     *     allowed_transitions: list<array{to: string, reason_required: bool}>,
     *     departure: array{id: int, date: string, return_date: string, itinerary_name: string, embark: string, festive: bool, yacht: array{id: int, code: string, name: string}},
     *     cabin: array{id: int, code: string, label: string}|null,
     *     cabin_label: string,
     *     contact: array{id: int, name: string, email: string|null, phone: string|null, country: string|null, preferred_channel: string},
     *     group: array{id: int, reference: string, name: string, coordinator: array{id: int, name: string}}|null,
     *     owner: array{id: int, name: string},
     *     agency: array{id: int, reference: string, name: string, commission_pct: int}|null,
     *     commission_pct: int|null,
     *     commission_amount: int,
     *     commission_approved: bool,
     *     commission_approved_by: array{id: int, name: string}|null,
     *     commission_approved_at: string|null,
     *     commission_reason: string|null,
     *     request: array{preferred_channel: string, travel_advisor: bool, notes: string|null, hold: array{expires_at: string|null, expired: bool, rule: string, remaining_business_minutes: int}, sla: array{due_at: string, remaining_minutes: int, breached: bool}}|null,
     *     payment_links: list<array{id: int, kind: string, amount: int, stripe_id: string, url: string, status: string, mode: string, created_at: string}>
     * }
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'departure.yacht',
            'departure.itinerary',
            'cabin',
            'contact',
            'group.coordinator',
            'owner',
            'agency',
            'commissionApprovedBy',
            'ratesVersion',
            'bookingRequest',
            'activeClaims',
        ]);

        $actor = $request->user();
        $canAct = $actor instanceof User
            && app(BookingPolicy::class)->ownsOrMayActOnAny($actor, $this->resource);

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'request_reference' => $this->request_reference,
            'display_reference' => $this->displayReference(),
            'type' => $this->type->value,
            'status' => $this->status->value,
            'segment' => $this->segment()->value,
            'main_channel' => $this->main_channel->value,
            'channel_of_origin' => $this->channel_of_origin->value,
            'adults' => $this->adults,
            'children' => $this->children,
            'party_label' => $this->partyLabel(),
            'back_to_back' => $this->back_to_back,
            'total' => $this->total,
            'paid' => Ledger::paid($this->resource),
            'pledged' => Ledger::pledged($this->resource),
            'balance' => $this->balance(),
            'payments_count' => (int) ($this->resource->payments_count ?? $this->resource->payments()->count()),
            'deposit_pct' => $this->deposit_pct,
            'deposit_amount' => $this->depositAmount(),
            'balance_days' => $this->balance_days,
            'balance_due_date' => $this->balanceDueDate()->toDateString(),
            'overdue' => $this->isOverdue(),
            'overdue_days' => $this->overdueDays(),
            'wire_window_ends_at' => Iso::utc($this->wireWindowEndsAt()),
            'price_lines' => $this->price_lines,
            'rates_version' => [
                'id' => $this->ratesVersion->id,
                'version' => $this->ratesVersion->version,
            ],
            'internal_notes' => $this->internal_notes,
            'can_act' => $canAct,
            'allowed_transitions' => $actor instanceof User
                ? Transitions::allowedFor($this->resource, $actor)
                : [],
            'departure' => [
                'id' => $this->departure->id,
                'date' => $this->departure->date->toDateString(),
                'return_date' => $this->departure->returnDate()->toDateString(),
                'itinerary_name' => $this->departure->itinerary->name,
                'embark' => $this->departure->itinerary->embark,
                'festive' => $this->departure->festive,
                'yacht' => [
                    'id' => $this->departure->yacht->id,
                    'code' => $this->departure->yacht->code,
                    'name' => $this->departure->yacht->name,
                ],
            ],
            'cabin' => $this->cabin === null ? null : [
                'id' => $this->cabin->id,
                'code' => $this->cabin->code,
                'label' => $this->cabin->label,
            ],
            'cabin_label' => $this->cabinLabel(),
            'contact' => (new ContactResource($this->contact))->toArray($request),
            'group' => $this->groupPayload($this->group),
            'owner' => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ],
            'agency' => $this->agency === null ? null : [
                'id' => $this->agency->id,
                'reference' => $this->agency->reference,
                'name' => $this->agency->name,
                'commission_pct' => $this->agency->commission_pct,
            ],
            'commission_pct' => $this->commission_pct,
            'commission_amount' => $this->commissionAmount(),
            'commission_approved' => $this->commission_approved,
            'commission_approved_by' => $this->commissionApprovedBy === null ? null : [
                'id' => $this->commissionApprovedBy->id,
                'name' => $this->commissionApprovedBy->name,
            ],
            'commission_approved_at' => Iso::utc($this->commission_approved_at),
            'commission_reason' => $this->commission_reason,
            'request' => RequestSummary::for($this->resource),
            'payment_links' => $this->relationLoaded('paymentLinks')
                ? PaymentLinkResource::collection($this->paymentLinks)->resolve()
                : [],
        ];
    }

    /**
     * @return array{id: int, reference: string, name: string, coordinator: array{id: int, name: string}}|null
     */
    private function groupPayload(?Group $group): ?array
    {
        if (! $group instanceof Group) {
            return null;
        }

        $group->loadMissing('coordinator');

        return [
            'id' => $group->id,
            'reference' => $group->reference,
            'name' => $group->name,
            'coordinator' => [
                'id' => $group->coordinator->id,
                'name' => $group->coordinator->name,
            ],
        ];
    }
}
