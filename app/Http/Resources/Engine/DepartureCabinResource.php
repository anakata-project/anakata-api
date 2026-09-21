<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartureCabinResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{code: string, category: string, bookable: bool}
     */
    public function toArray(Request $request): array
    {
        /** @var array{code: string, category: string, bookable: bool} $cabin */
        $cabin = $this->resource;

        return $cabin;
    }
}
