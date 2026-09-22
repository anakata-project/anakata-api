<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Manifest;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Manifest
 */
class ManifestVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'departure_id' => $this->departure_id,
            'kind' => $this->kind->value,
            'version' => $this->version,
            'reason' => $this->reason->value,
            'generated_at' => Iso::utc($this->generated_at),
            'generated_by' => $this->generated_by === null ? null : [
                'id' => $this->generatedBy->id,
                'name' => $this->generatedBy->name,
            ],
            'passengers' => $this->passengers,
            'complete' => $this->complete,
            'purged_at' => Iso::utc($this->purged_at),
        ];
    }
}
