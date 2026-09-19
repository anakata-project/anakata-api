<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConfigKind;

class EngineSettingsVersion extends ConfigVersion
{
    protected $table = 'engine_settings_versions';

    public static function configKind(): ConfigKind
    {
        return ConfigKind::EngineSettings;
    }
}
