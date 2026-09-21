<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{id: string, retried: true} $resource
 */
class RetrySyncFailureResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{id: string, retried: true}
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
