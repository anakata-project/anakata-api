<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\SalesMaterialKind;
use App\Models\SalesMaterial;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SalesMaterial
 */
class SalesMaterialResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     kind: SalesMaterialKind,
     *     agency_id: int|null,
     *     version: int,
     *     bytes: int,
     *     mime: string,
     *     published: bool,
     *     uploaded_by: array{id: int, name: string}|null,
     *     updated_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        $uploader = $this->uploadedBy;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'kind' => $this->materialKind(),
            'agency_id' => $this->agency_id,
            'version' => $this->version,
            'bytes' => $this->bytes,
            'mime' => $this->mime,
            'published' => $this->published,
            'uploaded_by' => $uploader === null ? null : [
                'id' => $uploader->id,
                'name' => $uploader->name,
            ],
            'updated_at' => Iso::utc($this->updated_at),
        ];
    }

    private function materialKind(): SalesMaterialKind
    {
        return $this->kind;
    }
}
