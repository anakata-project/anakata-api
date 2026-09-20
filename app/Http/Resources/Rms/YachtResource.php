<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Yacht;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Yacht
 */
class YachtResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     cabins: list<array{id: int, code: string, label: string, category: string, sort: int}>
     * }
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('cabins');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'cabins' => CabinResource::collection($this->cabins)->resolve(),
        ];
    }
}
