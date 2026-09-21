<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     command: string,
 *     cadence: string,
 *     timezone: string|null,
 *     description: string,
 *     last_started_at: string|null,
 *     last_finished_at: string|null,
 *     last_outcome: string|null,
 *     last_output: string|null,
 *     next_run_at: string|null
 * } $resource
 */
class ScheduledJobResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     command: string,
     *     cadence: string,
     *     timezone: string|null,
     *     description: string,
     *     last_started_at: string|null,
     *     last_finished_at: string|null,
     *     last_outcome: string|null,
     *     last_output: string|null,
     *     next_run_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
