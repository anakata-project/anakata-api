<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\ConfigVersion;
use App\Services\Config\ConfigRegistry;
use App\Support\Config\ConfigDocument;

enum ConfigKind: string
{
    case Rates = 'rates';
    case BusinessRules = 'business_rules';
    case EngineSettings = 'engine_settings';

    public function slug(): string
    {
        return match ($this) {
            self::Rates => 'rates',
            self::BusinessRules => 'business-rules',
            self::EngineSettings => 'engine-settings',
        };
    }

    public function historyPrefix(): string
    {
        return $this->value;
    }

    public function cacheKey(): string
    {
        return "config:{$this->value}:current";
    }

    public function label(): string
    {
        return match ($this) {
            self::Rates => 'rates',
            self::BusinessRules => 'business rules',
            self::EngineSettings => 'engine settings',
        };
    }

    /**
     * @return class-string<ConfigVersion>
     */
    public function modelClass(): string
    {
        return ConfigRegistry::modelClass($this);
    }

    /**
     * @return class-string<ConfigDocument>
     */
    public function documentClass(): string
    {
        return ConfigRegistry::documentClass($this);
    }
}
