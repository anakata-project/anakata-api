<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\GuestResponseSource;
use App\Models\GuestResponse;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GuestResponse
 */
#[SchemaName('GuestResponseResource')]
class GuestResponseResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     guest_id: int,
     *     score: int,
     *     recommend: int|null,
     *     call_notes: string|null,
     *     source: GuestResponseSource
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guest_id' => $this->guest_id,
            'score' => $this->score,
            'recommend' => $this->recommend,
            'call_notes' => $this->call_notes,
            'source' => $this->source,
        ];
    }
}
