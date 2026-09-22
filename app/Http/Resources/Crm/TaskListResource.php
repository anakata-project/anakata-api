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
 *         title: string,
 *         context: string|null,
 *         kind: string,
 *         source: string,
 *         source_label: string,
 *         status: string,
 *         due_at: string,
 *         priority: string,
 *         owner: array{id: int, name: string}|null,
 *         needs_permission: string|null,
 *         contact: array{id: int, name: string}|null,
 *         deal: array{id: int, title: string}|null,
 *         booking: array{id: int|null, reference: string}|null,
 *         may_complete: bool
 *     }>,
 *     meta: array{kpis: array{open: int, breached: int, near: int, system: int, quote_sla_hours: int}}
 * } $resource
 */
#[SchemaName('TaskListResource')]
class TaskListResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     data: list<array{
     *         id: int,
     *         title: string,
     *         context: string|null,
     *         kind: string,
     *         source: string,
     *         source_label: string,
     *         status: string,
     *         due_at: string,
     *         priority: string,
     *         owner: array{id: int, name: string}|null,
     *         needs_permission: string|null,
     *         contact: array{id: int, name: string}|null,
     *         deal: array{id: int, title: string}|null,
     *         booking: array{id: int|null, reference: string}|null,
     *         may_complete: bool
     *     }>,
     *     meta: array{kpis: array{open: int, breached: int, near: int, system: int, quote_sla_hours: int}}
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
