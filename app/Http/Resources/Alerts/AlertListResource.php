<?php

declare(strict_types=1);

namespace App\Http\Resources\Alerts;

use App\Models\Alert;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     page: LengthAwarePaginator<int, Alert>,
 *     counts: array{INFO: int, WARN: int, CRITICAL: int}
 * } $resource
 */
#[SchemaName('AlertListResource')]
class AlertListResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $page = $this->resource['page'];

        return [
            'data' => AlertResource::collection($page->items())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'counts' => $this->resource['counts'],
            ],
        ];
    }
}
