<?php

declare(strict_types=1);

namespace App\Support\Agencies;

use App\Enums\AgencyStatus;
use App\Models\Agency;
use App\Support\BusinessHours;
use App\Support\BusinessTime;
use App\Support\Config\Documents\BusinessRulesDocument;

final class AgencySla
{
    /**
     * @return array{sla_business_days_elapsed: int, sla_breached: bool}
     */
    public static function for(Agency $agency, BusinessHours $hours, BusinessRulesDocument $rules): array
    {
        $elapsed = $hours->businessDaysElapsed($agency->requested_at, BusinessTime::now());
        $limit = $rules->sla->agencyApprovalBusinessDays;

        return [
            'sla_business_days_elapsed' => $elapsed,
            'sla_breached' => $agency->status === AgencyStatus::Pending && $elapsed >= $limit,
        ];
    }
}
