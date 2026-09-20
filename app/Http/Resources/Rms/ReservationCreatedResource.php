<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Booking;
use App\Support\Bookings\ReservationCreated;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReservationCreated
 */
class ReservationCreatedResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     bookings: list<array<string, mixed>>,
     *     group: array{id: int, reference: string, name: string, coordinator: array{id: int, name: string}}|null,
     *     warnings: list<string>
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var ReservationCreated $created */
        $created = $this->resource;

        $created->bookings->load([
            'departure.yacht',
            'cabin',
            'contact',
            'group.coordinator',
            'owner',
            'ratesVersion',
        ]);

        $group = $created->group;

        if ($group !== null) {
            $group->loadMissing('coordinator');
        }

        return [
            'bookings' => $created->bookings
                ->map(fn (Booking $booking): array => (new BookingResource($booking))->toArray($request))
                ->values()
                ->all(),
            'group' => $group === null ? null : [
                'id' => $group->id,
                'reference' => $group->reference,
                'name' => $group->name,
                'coordinator' => [
                    'id' => $group->coordinator->id,
                    'name' => $group->coordinator->name,
                ],
            ],
            'warnings' => $created->warnings,
        ];
    }
}
