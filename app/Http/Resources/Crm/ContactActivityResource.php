<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{id: int, kind: string, body: string, occurred_at: string} $resource
 */
#[SchemaName('ContactActivityResource')]
class ContactActivityResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{id: int, kind: string, body: string, occurred_at: string}
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
