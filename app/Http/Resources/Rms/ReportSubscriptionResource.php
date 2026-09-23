<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\ReportCadence;
use App\Models\ReportSubscription;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ReportSubscription $resource
 */
#[SchemaName('ReportSubscriptionResource')]
class ReportSubscriptionResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     definition_key: string,
     *     cadence: ReportCadence,
     *     send_at: string,
     *     weekday: int|null,
     *     day_of_month: int|null,
     *     window: string|null,
     *     active: bool
     * }
     */
    public function toArray(Request $request): array
    {
        $subscription = $this->resource;
        $window = $subscription->parameters['window'] ?? null;

        return [
            'id' => $subscription->id,
            'definition_key' => $subscription->definition_key,
            'cadence' => $subscription->cadence,
            'send_at' => $subscription->clock(),
            'weekday' => $subscription->weekday,
            'day_of_month' => $subscription->day_of_month,
            'window' => is_string($window) ? $window : null,
            'active' => $subscription->active,
        ];
    }
}
