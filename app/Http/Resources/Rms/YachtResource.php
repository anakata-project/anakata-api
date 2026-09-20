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
            'cabins' => $this->cabinPayloads(),
        ];
    }

    /**
     * @return list<array{id: int, code: string, label: string, category: string, sort: int}>
     */
    private function cabinPayloads(): array
    {
        $cabins = [];

        foreach ($this->cabins as $cabin) {
            $cabins[] = [
                'id' => $cabin->id,
                'code' => $cabin->code,
                'label' => $cabin->label,
                'category' => $cabin->category->value,
                'sort' => $cabin->sort,
            ];
        }

        return $cabins;
    }
}
