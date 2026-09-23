<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\Permission;
use App\Enums\ReportFormat;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     key: string,
 *     title: string,
 *     sentence: string,
 *     permission: Permission,
 *     formats: list<ReportFormat>,
 *     allowed: bool
 * } $resource
 */
#[SchemaName('ReportDefinitionResource')]
class ReportDefinitionResource extends JsonResource
{
    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     sentence: string,
     *     permission: Permission,
     *     formats: list<ReportFormat>,
     *     allowed: bool
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
