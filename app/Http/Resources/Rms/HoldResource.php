<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\HoldRule;
use App\Enums\HoldType;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Services\Config\CurrentConfig;
use App\Support\Bookings\HoldRuleText;
use App\Support\BusinessHours;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     type: string,
 *     client: string,
 *     departure: array{date: string, yacht: array{code: string, name: string}},
 *     cabin: string,
 *     expires_at: \DateTimeInterface|null,
 *     remaining_business_minutes: int,
 *     rule: string,
 *     reference: string|null
 * } $resource
 */
class HoldResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     type: string,
     *     client: string,
     *     departure: array{date: string, yacht: array{code: string, name: string}},
     *     cabin: string,
     *     expires_at: string|null,
     *     remaining_business_minutes: int,
     *     rule: string,
     *     reference: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array{type: string, client: string, departure: array{date: string, yacht: array{code: string, name: string}}, cabin: string, expires_at: \DateTimeInterface|null, remaining_business_minutes: int, rule: string, reference: string|null} $row */
        $row = $this->resource;

        return [
            'type' => $row['type'],
            'client' => $row['client'],
            'departure' => $row['departure'],
            'cabin' => $row['cabin'],
            'expires_at' => Iso::utc($row['expires_at']),
            'remaining_business_minutes' => $row['remaining_business_minutes'],
            'rule' => $row['rule'],
            'reference' => $row['reference'],
        ];
    }

    /**
     * @return array{
     *     type: string,
     *     client: string,
     *     departure: array{date: string, yacht: array{code: string, name: string}},
     *     cabin: string,
     *     expires_at: \DateTimeInterface|null,
     *     remaining_business_minutes: int,
     *     rule: string,
     *     reference: string|null
     * }
     */
    public static function fromBooking(Booking $booking): array
    {
        $booking->loadMissing(['departure.yacht', 'cabin', 'contact', 'bookingRequest', 'claims']);
        $rules = app(CurrentConfig::class)->businessRules();
        $hours = BusinessHours::fromDocument($rules);
        $hold = $booking->claims
            ->first(fn ($claim): bool => $claim->released_at === null);

        $expiresAt = $hold?->expires_at;
        $request = $booking->bookingRequest;
        $rule = $request instanceof BookingRequest ? $request->hold_rule : HoldRule::LongLead;
        $holdType = $hold?->hold_type;

        $booking->loadMissing('departure.yacht');

        return [
            'type' => $holdType instanceof HoldType ? $holdType->value : 'REQUEST',
            'client' => $booking->contact->name,
            'departure' => [
                'date' => $booking->departure->date->toDateString(),
                'yacht' => [
                    'code' => $booking->departure->yacht->code,
                    'name' => $booking->departure->yacht->name,
                ],
            ],
            'cabin' => $booking->cabinLabel(),
            'expires_at' => $expiresAt,
            'remaining_business_minutes' => $expiresAt === null
                ? 0
                : $hours->remainingBusinessMinutes(now(), $expiresAt),
            'rule' => HoldRuleText::tec004($rule, $rules),
            'reference' => $booking->displayReference(),
        ];
    }
}
