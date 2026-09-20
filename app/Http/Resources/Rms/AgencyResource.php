<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Agency;
use App\Models\Booking;
use App\Services\Config\CurrentConfig;
use App\Support\Agencies\AgencySla;
use App\Support\Agencies\PortalPreview;
use App\Support\BusinessHours;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Agency
 */
class AgencyResource extends JsonResource
{
    public static $wrap = null;

    public bool $detailed = false;

    /**
     * @return array{
     *     id: int,
     *     reference: string,
     *     name: string,
     *     contact: string,
     *     email: string,
     *     country: string|null,
     *     network: string|null,
     *     commission_pct: int,
     *     payment_terms: string,
     *     status: string,
     *     requested_at: string,
     *     decided_at: string|null,
     *     decided_by: array{id: int, name: string}|null,
     *     decision_reason: string|null,
     *     sla_business_days_elapsed: int,
     *     sla_breached: bool,
     *     users: list<array{id: int, name: string, email: string, status: string}>
     * }|array{
     *     id: int,
     *     reference: string,
     *     name: string,
     *     contact: string,
     *     email: string,
     *     country: string|null,
     *     network: string|null,
     *     commission_pct: int,
     *     payment_terms: string,
     *     status: string,
     *     requested_at: string,
     *     decided_at: string|null,
     *     decided_by: array{id: int, name: string}|null,
     *     decision_reason: string|null,
     *     sla_business_days_elapsed: int,
     *     sla_breached: bool,
     *     users: list<array{id: int, name: string, email: string, status: string}>,
     *     revenue: int,
     *     commission_accrued: int,
     *     bookings_count: int,
     *     bookings: list<array{id: int, reference: string|null, status: string, total: int, commission_pct: int|null, commission_amount: int, commission_approved: bool, departure_date: string, client: string}>,
     *     portal_preview: array{commission_pct: int, net_rates: list<array{year: int, suite_pp: int, owner_pp: int, charter_week: int}>}
     * }
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['users', 'decidedBy']);

        $config = app(CurrentConfig::class);
        $rules = $config->businessRules();
        $hours = BusinessHours::fromDocument($rules);
        $sla = AgencySla::for($this->resource, $hours, $rules);

        $payload = [
            'id' => $this->id,
            'reference' => $this->reference,
            'name' => $this->name,
            'contact' => $this->contact,
            'email' => $this->email,
            'country' => $this->country,
            'network' => $this->network,
            'commission_pct' => $this->commission_pct,
            'payment_terms' => $this->payment_terms,
            'status' => $this->status->value,
            'requested_at' => Iso::utc($this->requested_at),
            'decided_at' => $this->decided_at !== null ? Iso::utc($this->decided_at) : null,
            'decided_by' => $this->decidedBy === null ? null : [
                'id' => $this->decidedBy->id,
                'name' => $this->decidedBy->name,
            ],
            'decision_reason' => $this->decision_reason,
            'sla_business_days_elapsed' => $sla['sla_business_days_elapsed'],
            'sla_breached' => $sla['sla_breached'],
            'users' => $this->users->map(fn ($user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status->value,
            ])->values()->all(),
        ];

        if (! $this->detailed) {
            return $payload;
        }

        $this->resource->loadMissing(['bookings.departure', 'bookings.contact']);

        $bookings = $this->bookings;
        $revenue = (int) $bookings->sum('total');
        $accrued = (int) $bookings
            ->filter(fn (Booking $booking): bool => $booking->commission_approved)
            ->sum(fn (Booking $booking): int => $booking->commissionAmount());

        $payload['revenue'] = $revenue;
        $payload['commission_accrued'] = $accrued;
        $payload['bookings_count'] = $bookings->count();
        $payload['bookings'] = $bookings->map(fn (Booking $booking): array => [
            'id' => $booking->id,
            'reference' => $booking->reference,
            'status' => $booking->status->value,
            'total' => $booking->total,
            'commission_pct' => $booking->commission_pct,
            'commission_amount' => $booking->commissionAmount(),
            'commission_approved' => $booking->commission_approved,
            'departure_date' => $booking->departure->date->toDateString(),
            'client' => $booking->contact->name,
        ])->values()->all();
        $payload['portal_preview'] = PortalPreview::for($this->resource, $config->rates());

        return $payload;
    }
}
