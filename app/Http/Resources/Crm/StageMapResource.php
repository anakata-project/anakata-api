<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     data: list<array{
 *         stage: string,
 *         label: string,
 *         owner: string,
 *         enters_when: string,
 *         rms_statuses: list<string>,
 *         leaves_when: string
 *     }>
 * } $resource
 */
#[SchemaName('StageMapResource')]
class StageMapResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     data: list<array{
     *         stage: string,
     *         label: string,
     *         owner: string,
     *         enters_when: string,
     *         rms_statuses: list<string>,
     *         leaves_when: string
     *     }>
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
