<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EngineCountryResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{code: string, name: string}
     */
    public function toArray(Request $request): array
    {
        /** @var array{code: string, name: string} $country */
        $country = $this->resource;

        return $country;
    }
}
