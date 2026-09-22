<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     data: list<array{layer: string, captured_by: string, stored_on: string, used_for: string}>,
 *     conflict: string
 * } $resource
 */
#[SchemaName('AttributionModelResource')]
class AttributionModelResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     data: list<array{layer: string, captured_by: string, stored_on: string, used_for: string}>,
     *     conflict: string
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
