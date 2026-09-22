<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\PreferenceSource;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     current: array{
 *         id: int,
 *         version: int,
 *         source: PreferenceSource,
 *         answered_at: string|null,
 *         purged_at: string|null,
 *         recorded_by: array{id: int, name: string|null}|null,
 *         answers: array<string, string>,
 *         accessibility_provided: bool,
 *         emergency_contact_provided: bool,
 *         accessibility?: string|null,
 *         emergency_contact?: string|null
 *     }|null,
 *     versions: list<array{
 *         id: int,
 *         version: int,
 *         source: PreferenceSource,
 *         answered_at: string|null,
 *         purged_at: string|null,
 *         recorded_by: array{id: int, name: string|null}|null,
 *         answers: array<string, string>,
 *         accessibility_provided: bool,
 *         emergency_contact_provided: bool,
 *         accessibility?: string|null,
 *         emergency_contact?: string|null
 *     }>
 * } $resource
 */
#[SchemaName('GuestPreferencesResource')]
class GuestPreferencesResource extends JsonResource
{
    /**
     * @return array{
     *     current: array{
     *         id: int,
     *         version: int,
     *         source: PreferenceSource,
     *         answered_at: string|null,
     *         purged_at: string|null,
     *         recorded_by: array{id: int, name: string|null}|null,
     *         answers: array<string, string>,
     *         accessibility_provided: bool,
     *         emergency_contact_provided: bool,
     *         accessibility?: string|null,
     *         emergency_contact?: string|null
     *     }|null,
     *     versions: list<array{
     *         id: int,
     *         version: int,
     *         source: PreferenceSource,
     *         answered_at: string|null,
     *         purged_at: string|null,
     *         recorded_by: array{id: int, name: string|null}|null,
     *         answers: array<string, string>,
     *         accessibility_provided: bool,
     *         emergency_contact_provided: bool,
     *         accessibility?: string|null,
     *         emergency_contact?: string|null
     *     }>
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
