<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Cabin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cabin
 */
class CabinResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     label: string,
     *     category: string,
     *     sort: int
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'category' => $this->category->value,
            'sort' => $this->sort,
        ];
    }
}
