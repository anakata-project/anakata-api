<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     data: list<array{
 *         id: int,
 *         reference: string|null,
 *         departure: string|null,
 *         status: string,
 *         charges_total: int,
 *         measures: array{redeemed: bool, first_touch: bool, last_touch: bool}
 *     }>,
 *     meta: array{current_page: int, last_page: int, per_page: int, total: int}
 * } $resource
 */
#[SchemaName('CampaignBookingPageResource')]
class CampaignBookingPageResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     data: list<array{
     *         id: int,
     *         reference: string|null,
     *         departure: string|null,
     *         status: string,
     *         charges_total: int,
     *         measures: array{redeemed: bool, first_touch: bool, last_touch: bool}
     *     }>,
     *     meta: array{current_page: int, last_page: int, per_page: int, total: int}
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
