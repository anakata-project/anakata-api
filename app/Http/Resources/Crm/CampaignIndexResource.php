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
 *         name: string,
 *         audience: string|null,
 *         media_spend: int,
 *         status: string,
 *         utm_campaign: string|null,
 *         owner: array{id: int, name: string}|null,
 *         offer: array{
 *             id: int,
 *             code: string,
 *             name: string,
 *             type: string,
 *             value_text: string,
 *             channel: string,
 *             status: string,
 *             booking_window: array{from: string|null, to: string|null},
 *             travel_window: array{from: string|null, to: string|null}
 *         }|null,
 *         redeemed: int,
 *         revenue: int,
 *         attributed_first: array{count: int, revenue: int},
 *         attributed_last: array{count: int, revenue: int},
 *         trade: int,
 *         roas: string|null,
 *         sends: null,
 *         clicks: null
 *     }>,
 *     meta: array{notes: array{sends: string}}
 * } $resource
 */
#[SchemaName('CampaignIndexResource')]
class CampaignIndexResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     data: list<array{
     *         id: int,
     *         name: string,
     *         audience: string|null,
     *         media_spend: int,
     *         status: string,
     *         utm_campaign: string|null,
     *         owner: array{id: int, name: string}|null,
     *         offer: array{
     *             id: int,
     *             code: string,
     *             name: string,
     *             type: string,
     *             value_text: string,
     *             channel: string,
     *             status: string,
     *             booking_window: array{from: string|null, to: string|null},
     *             travel_window: array{from: string|null, to: string|null}
     *         }|null,
     *         redeemed: int,
     *         revenue: int,
     *         attributed_first: array{count: int, revenue: int},
     *         attributed_last: array{count: int, revenue: int},
     *         trade: int,
     *         roas: string|null,
     *         sends: null,
     *         clicks: null
     *     }>,
     *     meta: array{notes: array{sends: string}}
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
