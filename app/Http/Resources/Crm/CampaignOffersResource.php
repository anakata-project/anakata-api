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
 *         code: string,
 *         name: string,
 *         type: string,
 *         value_text: string,
 *         channel: string,
 *         status: string,
 *         booking_window: array{from: string|null, to: string|null},
 *         travel_window: array{from: string|null, to: string|null}
 *     }>
 * } $resource
 */
#[SchemaName('CampaignOffersResource')]
class CampaignOffersResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     data: list<array{
     *         id: int,
     *         code: string,
     *         name: string,
     *         type: string,
     *         value_text: string,
     *         channel: string,
     *         status: string,
     *         booking_window: array{from: string|null, to: string|null},
     *         travel_window: array{from: string|null, to: string|null}
     *     }>
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
