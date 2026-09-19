<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConfigKind;

class BusinessRuleVersion extends ConfigVersion
{
    protected $table = 'business_rule_versions';

    public static function configKind(): ConfigKind
    {
        return ConfigKind::BusinessRules;
    }
}
