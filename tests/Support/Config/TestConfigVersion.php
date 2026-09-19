<?php

declare(strict_types=1);

namespace Tests\Support\Config;

use App\Enums\ConfigKind;
use App\Models\ConfigVersion;

class TestConfigVersion extends ConfigVersion
{
    protected $table = 'test_config_versions';

    public static function configKind(): ConfigKind
    {
        return ConfigKind::Rates;
    }
}
