<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{
 *     matched: list<array<string, mixed>>,
 *     in_gateway_not_rms: list<array<string, mixed>>,
 *     to_review: list<array<string, mixed>>,
 *     counts: array{matched: int, in_gateway_not_rms: int, to_review: int},
 *     meta: array{from: string, to: string, mode: string},
 *     note: string
 * } $resource
 */
class ReconciliationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     matched: list<array<string, mixed>>,
     *     in_gateway_not_rms: list<array<string, mixed>>,
     *     to_review: list<array<string, mixed>>,
     *     counts: array{matched: int, in_gateway_not_rms: int, to_review: int},
     *     meta: array{from: string, to: string, mode: string},
     *     note: string
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
