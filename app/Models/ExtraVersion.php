<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConfigKind;

class ExtraVersion extends ConfigVersion
{
    protected $table = 'extra_versions';

    public static function configKind(): ConfigKind
    {
        return ConfigKind::Extras;
    }
}
