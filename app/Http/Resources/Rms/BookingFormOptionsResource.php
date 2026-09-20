<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     main: list<array{value: string, label: string, trade: bool}>,
 *     origin: list<array{group: string, options: list<array{value: string, label: string}>}>,
 *     preferred: list<array{value: string, label: string}>,
 *     guests: array{child_min_age: int, child_max_age: int, max_per_cabin: int}
 * } $resource
 */
class BookingFormOptionsResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     main: list<array{value: string, label: string, trade: bool}>,
     *     origin: list<array{group: string, options: list<array{value: string, label: string}>}>,
     *     preferred: list<array{value: string, label: string}>,
     *     guests: array{child_min_age: int, child_max_age: int, max_per_cabin: int}
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
