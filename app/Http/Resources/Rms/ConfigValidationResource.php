<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Services\Config\ValidationReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ValidationReport
 */
class ConfigValidationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     errors: array<string, list<string>>,
     *     warnings: list<array{path: string, message: string}>,
     *     changes: list<array{path: string, label: string, from: mixed, to: mixed}>
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
