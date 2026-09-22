<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\ManifestKind;
use App\Enums\ManifestReason;
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
     * @return array{
     *     id: int,
     *     departure_id: int,
     *     kind: ManifestKind,
     *     version: int,
     *     reason: ManifestReason,
     *     generated_at: string|null,
     *     generated_by: array{id: int, name: string}|null,
     *     passengers: int,
     *     complete: int,
     *     purged_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'departure_id' => $this->departure_id,
            'kind' => $this->kind,
            'version' => $this->version,
            'reason' => $this->reason,
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
