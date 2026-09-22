<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     columns: list<array{
 *         stage: string,
 *         label: string,
 *         owner: string,
 *         sla: string,
 *         total: int,
 *         weighted_total: int|null,
 *         deals: list<array{
 *             id: int,
 *             title: string,
 *             contact: array{id: int, name: string},
 *             type: string,
 *             owner: array{id: int, name: string}|null,
 *             value: int,
 *             value_label: string,
 *             sla_state: string|null,
 *             booking: array{reference: string, status: string, departure_date: string}|null,
 *             may_move: bool
 *         }>
 *     }>,
 *     meta: array{kpis: array{
 *         collected: int,
 *         scheduled_in: int,
 *         awaiting_first_payment: int,
 *         open_pipeline_count: int,
 *         open_pipeline_value: int,
 *         weighted_forecast: int,
 *         overdue: int
 *     }}
 * } $resource
 */
#[SchemaName('PipelineResource')]
class PipelineResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     columns: list<array{
     *         stage: string,
     *         label: string,
     *         owner: string,
     *         sla: string,
     *         total: int,
     *         weighted_total: int|null,
     *         deals: list<array{
     *             id: int,
     *             title: string,
     *             contact: array{id: int, name: string},
     *             type: string,
     *             owner: array{id: int, name: string}|null,
     *             value: int,
     *             value_label: string,
     *             sla_state: string|null,
     *             booking: array{reference: string, status: string, departure_date: string}|null,
     *             may_move: bool
     *         }>
     *     }>,
     *     meta: array{kpis: array{
     *         collected: int,
     *         scheduled_in: int,
     *         awaiting_first_payment: int,
     *         open_pipeline_count: int,
     *         open_pipeline_value: int,
     *         weighted_forecast: int,
     *         overdue: int
     *     }}
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
