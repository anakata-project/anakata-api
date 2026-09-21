<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WaitlistEntry
 */
class EngineWaitlistResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     departure_id: int,
     *     cabin_category: string,
     *     source: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'departure_id' => $this->departure_id,
            'cabin_category' => $this->cabin_category->value,
            'source' => $this->source->value,
        ];
    }
}
