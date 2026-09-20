<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{
 *     available: bool,
 *     current_total: int,
 *     new_total: int,
 *     difference: int,
 *     new_price_lines: list<array{code: string, label: string, amount: int}>,
 *     sailing_year_changes: bool,
 *     festive_changes: bool,
 *     warnings: list<string>
 * } $resource
 */
class MovePreviewResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     available: bool,
     *     current_total: int,
     *     new_total: int,
     *     difference: int,
     *     new_price_lines: list<array{code: string, label: string, amount: int}>,
     *     sailing_year_changes: bool,
     *     festive_changes: bool,
     *     warnings: list<string>
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
