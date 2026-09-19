<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConfigKind;

class RateVersion extends ConfigVersion
{
    protected $table = 'rate_versions';

    public static function configKind(): ConfigKind
    {
        return ConfigKind::Rates;
    }
}
