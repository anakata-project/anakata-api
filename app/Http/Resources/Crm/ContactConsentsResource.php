<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     current: list<array{purpose: string, label: string, granted: bool|null, version: string|null, captured_at: string|null, capture_point: string|null, recorded_by: array{id: int, name: string}|null, how_obtained: string|null, ip_present: bool}>,
 *     history: list<array{purpose: string, label: string, granted: bool, version: string, captured_at: string, capture_point: string, recorded_by: array{id: int, name: string}|null, how_obtained: string|null, ip_present: bool}>
 * } $resource
 */
#[SchemaName('ContactConsentsResource')]
class ContactConsentsResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     current: list<array{purpose: string, label: string, granted: bool|null, version: string|null, captured_at: string|null, capture_point: string|null, recorded_by: array{id: int, name: string}|null, how_obtained: string|null, ip_present: bool}>,
     *     history: list<array{purpose: string, label: string, granted: bool, version: string, captured_at: string, capture_point: string, recorded_by: array{id: int, name: string}|null, how_obtained: string|null, ip_present: bool}>
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
