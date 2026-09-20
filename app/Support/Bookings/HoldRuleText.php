<?php

declare(strict_types=1);

namespace App\Support\Bookings;

use App\Enums\HoldRule;
use App\Support\Config\Documents\BusinessRulesDocument;

final class HoldRuleText
{
    public static function tec004(HoldRule $rule, BusinessRulesDocument $rules): string
    {
        return match ($rule) {
            HoldRule::NearTerm => 'TEC-004 · '.$rules->holds->nearTermBusinessHours.' business hours (near-term)',
            HoldRule::LongLead => 'TEC-004 · '.$rules->holds->longLeadBusinessDays.' business days (long-lead)',
        };
    }
}
