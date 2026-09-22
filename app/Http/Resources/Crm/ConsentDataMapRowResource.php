<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{data: string, stored_in: string, in_crm: string, retention: string, rule_key: string|null, rule_value: int|null} $resource
 */
#[SchemaName('ConsentDataMapRowResource')]
class ConsentDataMapRowResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{data: string, stored_in: string, in_crm: string, retention: string, rule_key: string|null, rule_value: int|null}
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
