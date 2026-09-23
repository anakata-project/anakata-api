<?php

declare(strict_types=1);

namespace App\Http\Resources\Portal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{year: int, suite_pp: int, owner_pp: int, charter_week: int} $resource
 */
class PortalNetRateResource extends JsonResource
{
    /**
     * @return array{year: int, suite_pp: int, owner_pp: int, charter_week: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'year' => (int) $this->resource['year'],
            'suite_pp' => (int) $this->resource['suite_pp'],
            'owner_pp' => (int) $this->resource['owner_pp'],
            'charter_week' => (int) $this->resource['charter_week'],
        ];
    }
}
