<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Manifest;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{created: bool, message: string, manifest: Manifest} $resource
 */
#[SchemaName('ManifestIssuedResource')]
class ManifestIssuedResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{created: bool, message: string, data: ManifestVersionResource}
     */
    public function toArray(Request $request): array
    {
        return [
            'created' => (bool) $this->resource['created'],
            'message' => $this->resource['message'],
            'data' => new ManifestVersionResource($this->resource['manifest']),
        ];
    }
}
