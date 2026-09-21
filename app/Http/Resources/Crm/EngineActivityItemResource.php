<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{at: string, name: string, contact: string, detail: string, side: string} $resource
 */
class EngineActivityItemResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{at: string, name: string, contact: string, detail: string, side: string}
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
