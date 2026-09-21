<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{name: string, family: string, producer: string, listeners: list<string>} $resource
 */
class EventCatalogueResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{name: string, family: string, producer: string, listeners: list<string>}
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
