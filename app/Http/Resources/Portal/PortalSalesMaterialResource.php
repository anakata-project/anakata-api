<?php

declare(strict_types=1);

namespace App\Http\Resources\Portal;

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
     *     kind: string,
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
            'kind' => $this->kind->value,
            'size' => $this->bytes,
            'version' => $this->version,
            'updated' => Iso::utc($this->updated_at),
        ];
    }
}
