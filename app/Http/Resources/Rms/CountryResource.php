<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{code: string, name: string} $resource
 */
class CountryResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{code: string, name: string}
     */
    public function toArray(Request $request): array
    {
        /** @var array{code: string, name: string} $row */
        $row = $this->resource;

        return [
            'code' => $row['code'],
            'name' => $row['name'],
        ];
    }
}
