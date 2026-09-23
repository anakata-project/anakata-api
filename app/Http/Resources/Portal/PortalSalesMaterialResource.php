<?php

declare(strict_types=1);

namespace App\Http\Resources\Portal;

use App\Enums\SalesMaterialKind;
use App\Models\SalesMaterial;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SalesMaterial
 */
class PortalSalesMaterialResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     kind: SalesMaterialKind,
     *     size: int,
     *     version: int,
     *     updated: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'kind' => $this->materialKind(),
            'size' => $this->bytes,
            'version' => $this->version,
            'updated' => Iso::utc($this->updated_at),
        ];
    }

    private function materialKind(): SalesMaterialKind
    {
        return $this->kind;
    }
}
