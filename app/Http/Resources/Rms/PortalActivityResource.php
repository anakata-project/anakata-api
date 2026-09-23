<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     at: string,
 *     event: string,
 *     agency_user: array{id: int|null, name: string},
 *     references: list<string>|null,
 *     material: array{id: int, title: string, version: int}|null
 * } $resource
 */
class PortalActivityResource extends JsonResource
{
    /**
     * @return array{
     *     at: string,
     *     event: string,
     *     agency_user: array{id: int|null, name: string},
     *     references: list<string>|null,
     *     material: array{id: int, title: string, version: int}|null
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
