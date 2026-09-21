<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{object: string, field_group: string, system_of_record: string, read_by: string, rule: string, code: string|null} $resource
 */
class FieldOwnershipResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{object: string, field_group: string, system_of_record: string, read_by: string, rule: string, code: string|null}
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
