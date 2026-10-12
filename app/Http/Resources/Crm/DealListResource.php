<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     id: int,
 *     title: string,
 *     stage: string,
 *     stage_label: string,
 *     contact: array{id: int, name: string},
 *     type: string,
 *     owner: array{id: int, name: string}|null,
 *     value: int,
 *     value_label: string,
 *     sla_state: string|null,
 *     booking: array{reference: string, status: string, departure_date: string}|null,
 *     may_move: bool
 * } $resource
 */
#[SchemaName('DealListResource')]
class DealListResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     stage: string,
     *     stage_label: string,
     *     contact: array{id: int, name: string},
     *     type: string,
     *     owner: array{id: int, name: string}|null,
     *     value: int,
     *     value_label: string,
     *     sla_state: string|null,
     *     booking: array{reference: string, status: string, departure_date: string}|null,
     *     may_move: bool
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
