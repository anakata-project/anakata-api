<?php

declare(strict_types=1);

namespace App\Http\Resources\Portal;

use App\Models\Booking;
use App\Models\CommissionPayout;
use App\Services\Config\CurrentConfig;
use App\Support\Commissions\Accrual;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class PortalCommissionResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     reference: string|null,
     *     rate: int|null,
     *     commission_amount: int,
     *     payable_date: string,
     *     status: string,
     *     payout: array{paid_on: string, reference: string|null}|null
     * }
     */
    public function toArray(Request $request): array
    {
        $rules = app(CurrentConfig::class)->businessRules();

        return [
            'reference' => $this->reference,
            'rate' => $this->commission_pct,
            'commission_amount' => $this->resource->commissionAmount(),
            'payable_date' => Accrual::payableDate($this->resource, $rules)->toDateString(),
            'status' => Accrual::status($this->resource, $rules)->value,
            'payout' => $this->commissionPayout instanceof CommissionPayout ? [
                'paid_on' => $this->commissionPayout->paid_on->toDateString(),
                'reference' => $this->reference,
            ] : null,
        ];
    }
}
