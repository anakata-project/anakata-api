<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{url: string} $resource
 */
class CompleteLinkResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{url: string}
     */
    public function toArray(Request $request): array
    {
        /** @var array{url: string} $payload */
        $payload = $this->resource;

        return [
            'url' => $payload['url'],
        ];
    }
}
