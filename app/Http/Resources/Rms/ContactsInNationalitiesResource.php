<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read list<array{nationality: string, country_name: string, guests: int, bookings: int}> $nationalities
 * @property-read int $unknown
 * @property-read int $total_guests
 */
class ContactsInNationalitiesResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     nationalities: list<array{nationality: string, country_name: string, guests: int, bookings: int}>,
     *     unknown: int,
     *     total_guests: int
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array{nationalities: list<array{nationality: string, country_name: string, guests: int, bookings: int}>, unknown: int, total_guests: int} $payload */
        $payload = $this->resource;

        return [
            'nationalities' => $payload['nationalities'],
            'unknown' => $payload['unknown'],
            'total_guests' => $payload['total_guests'],
        ];
    }
}
