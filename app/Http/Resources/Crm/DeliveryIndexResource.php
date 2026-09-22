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
 *         booking: array{id: int, reference: string|null},
 *         client: string|null,
 *         document: array{kind: string, label: string, version: int, reason: string|null}|null,
 *         delivery_kind: string,
 *         delivery_kind_label: string,
 *         channel: string,
 *         status: string,
 *         at: string|null,
 *         detail: string|null,
 *         recipient_count: int,
 *         triggered_by: string,
 *         superseded: bool,
 *         rms_path: string
 *     }>,
 *     meta: array{
 *         current_page: int,
 *         last_page: int,
 *         per_page: int,
 *         total: int,
 *         kpis: array{sent_today: int, failed: int, blocked: int, queued_over_15_minutes: int},
 *         notes: array{engagement: string}
 *     }
 * } $resource
 */
#[SchemaName('DeliveryIndexResource')]
class DeliveryIndexResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     data: list<array{
     *         id: int,
     *         booking: array{id: int, reference: string|null},
     *         client: string|null,
     *         document: array{kind: string, label: string, version: int, reason: string|null}|null,
     *         delivery_kind: string,
     *         delivery_kind_label: string,
     *         channel: string,
     *         status: string,
     *         at: string|null,
     *         detail: string|null,
     *         recipient_count: int,
     *         triggered_by: string,
     *         superseded: bool,
     *         rms_path: string
     *     }>,
     *     meta: array{
     *         current_page: int,
     *         last_page: int,
     *         per_page: int,
     *         total: int,
     *         kpis: array{sent_today: int, failed: int, blocked: int, queued_over_15_minutes: int},
     *         notes: array{engagement: string}
     *     }
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
