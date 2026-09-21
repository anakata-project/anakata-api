<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Consent;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Consent
 */
class ConsentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     document: string,
     *     version: string,
     *     accepted_at: string,
     *     ip: string|null,
     *     source: string,
     *     how_obtained: string|null,
     *     recorded_by: int|null,
     *     withdrawn: bool
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document' => $this->document->value,
            'version' => $this->version,
            'accepted_at' => Iso::utc($this->accepted_at),
            'ip' => $this->ip,
            'source' => $this->source->value,
            'how_obtained' => $this->how_obtained,
            'recorded_by' => $this->recorded_by,
            'withdrawn' => $this->withdrawn,
        ];
    }
}
