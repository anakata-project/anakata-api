<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Support\BusinessHours;
use App\Support\BusinessTime;
use App\Support\Config\Documents\BusinessRulesDocument;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class TaskDue
{
    public static function responseHours(CarbonInterface $from, BusinessRulesDocument $rules): CarbonImmutable
    {
        return BusinessHours::fromDocument($rules)->addBusinessHours($from, $rules->sla->responseHours);
    }

    public static function businessDays(CarbonInterface $from, int $days, BusinessRulesDocument $rules): CarbonImmutable
    {
        return BusinessHours::fromDocument($rules)->endOfNthBusinessDay($from, $days);
    }

    public static function endOfGalapagosDay(CarbonInterface $instant): CarbonImmutable
    {
        return CarbonImmutable::instance($instant)->setTimezone(BusinessTime::zone())->endOfDay()->utc();
    }
}
