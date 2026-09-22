<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Enums\CommissionAccrualStatus;
use App\Models\Agency;
use App\Services\Config\CurrentConfig;
use App\Support\Agencies\PortalPreview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     commission_pct: int,
 *     net_rates: list<array{year: int, suite_pp: int, owner_pp: int, charter_week: int}>,
 *     bookings: list<array{reference: string|null, lead_guest: string, departure_date: string, status: string, net_due: int}>,
 *     commissions: list<array{reference: string|null, rate: int|null, commission_amount: int, payable_date: string, status: CommissionAccrualStatus}>,
 *     sales_materials: array{items: list<string>, note: string}
 * } $resource
 */
class AgencyPortalPreviewResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(mixed $resource)
    {
        if ($resource instanceof Agency) {
            $config = app(CurrentConfig::class);
            $resource = PortalPreview::view($resource, $config->rates(), $config->businessRules());
        }

        parent::__construct($resource);
    }

    /**
     * @return array{
     *     commission_pct: int,
     *     net_rates: list<array{year: int, suite_pp: int, owner_pp: int, charter_week: int}>,
     *     bookings: list<array{reference: string|null, lead_guest: string, departure_date: string, status: string, net_due: int}>,
     *     commissions: list<array{reference: string|null, rate: int|null, commission_amount: int, payable_date: string, status: CommissionAccrualStatus}>,
     *     sales_materials: array{items: list<string>, note: string}
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
