<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use App\Models\CharterEnquiry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CharterEnquiry
 */
class EngineCharterEnquiryResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     status: string,
     *     source: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'source' => $this->source->value,
        ];
    }
}
