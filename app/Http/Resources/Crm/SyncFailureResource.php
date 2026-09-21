<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{id: string, kind: string, name: string, at: string, detail: string} $resource
 */
class SyncFailureResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{id: string, kind: string, name: string, at: string, detail: string}
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
