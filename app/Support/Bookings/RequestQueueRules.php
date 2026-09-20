<?php

declare(strict_types=1);

namespace App\Support\Bookings;

use App\Services\Config\CurrentConfig;

final class RequestQueueRules
{
    /**
     * @return array{
     *     near_term_business_hours: int,
     *     long_lead_business_days: int,
     *     near_term_max_days: int,
     *     response_hours: int,
     *     business_day_minutes: int,
     *     cabin_deposit_pct: int
     * }
     */
    public static function fromConfig(CurrentConfig $config): array
    {
        $holds = $config->businessRules()->holds;
        [$startHour, $startMinute] = array_map(intval(...), explode(':', $holds->businessDayStart));
        [$endHour, $endMinute] = array_map(intval(...), explode(':', $holds->businessDayEnd));
        $businessDayMinutes = ($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute);

        return [
            'near_term_business_hours' => $holds->nearTermBusinessHours,
            'long_lead_business_days' => $holds->longLeadBusinessDays,
            'near_term_max_days' => $holds->nearTermMaxDays,
            'response_hours' => $config->businessRules()->sla->responseHours,
            'business_day_minutes' => $businessDayMinutes,
            'cabin_deposit_pct' => $config->rates()->terms->cabinDepositPct,
        ];
    }
}
