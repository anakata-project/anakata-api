<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{accepted: int, duplicate: int} $resource
 */
class EngineEventsAcceptedResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{accepted: int, duplicate: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'accepted' => (int) $this->resource['accepted'],
            'duplicate' => (int) $this->resource['duplicate'],
        ];
    }
}
