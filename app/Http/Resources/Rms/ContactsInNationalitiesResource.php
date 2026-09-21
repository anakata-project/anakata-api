<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     nationalities: list<array{nationality: string, country_name: string, guests: int, bookings: int}>,
 *     unknown: int,
 *     total_guests: int
 * } $resource
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
        return $this->resource;
    }
}
