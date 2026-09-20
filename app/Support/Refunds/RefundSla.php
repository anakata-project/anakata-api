<?php

declare(strict_types=1);

namespace App\Support\Refunds;

use App\Models\RefundRequest;
use App\Support\BusinessHours;
use App\Support\BusinessTime;
use App\Support\Config\Documents\BusinessRulesDocument;

final class RefundSla
{
    /**
     * @return array{business_days_remaining: int, sla_breached: bool}
     */
    public static function for(RefundRequest $request, BusinessHours $hours, BusinessRulesDocument $rules): array
    {
        $now = BusinessTime::now();
        $elapsed = $hours->businessDaysElapsed($request->cancelled_at, $now);
        $limit = $rules->sla->refundBusinessDays;
        $open = $request->status->isOpen();

        return [
            'business_days_remaining' => max(0, $limit - $elapsed),
            'sla_breached' => $open && $now->greaterThan($request->due_by),
        ];
    }
}
