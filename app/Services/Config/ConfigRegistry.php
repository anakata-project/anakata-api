<?php

declare(strict_types=1);

namespace App\Services\Config;

use App\Enums\ConfigKind;
use App\Models\ConfigVersion;
use App\Support\Config\ConfigDocument;
use LogicException;

final class ConfigRegistry
{
    /**
     * @var array<string, class-string<ConfigVersion>>
     */
    private static array $models = [];

    /**
     * @var array<string, class-string<ConfigDocument>>
     */
    private static array $documents = [];

    /**
     * @var array<string, array<string, mixed>|null>
     */
    private static array $initials = [];

    /**
     * @param  class-string<ConfigVersion>  $modelClass
     * @param  class-string<ConfigDocument>  $documentClass
     * @param  array<string, mixed>|null  $initialDocument
     */
    public static function register(
        ConfigKind $kind,
        string $modelClass,
        string $documentClass,
        ?array $initialDocument = null,
    ): void {
        self::$models[$kind->value] = $modelClass;
        self::$documents[$kind->value] = $documentClass;
        self::$initials[$kind->value] = $initialDocument;
    }

    public static function reset(): void
    {
        self::$models = [];
        self::$documents = [];
        self::$initials = [];
    }

    public static function has(ConfigKind $kind): bool
    {
        return isset(self::$models[$kind->value], self::$documents[$kind->value]);
    }

    /**
     * @return list<ConfigKind>
     */
    public static function kinds(): array
    {
        return array_values(array_filter(
            ConfigKind::cases(),
            self::has(...),
        ));
    }

    /**
     * @return class-string<ConfigVersion>
     */
    public static function modelClass(ConfigKind $kind): string
    {
        return self::$models[$kind->value] ?? throw new LogicException(match ($kind) {
            ConfigKind::Rates => 'RateVersion is added in sprint 2 task 02.',
            ConfigKind::BusinessRules => 'BusinessRuleVersion is added in sprint 2 task 04.',
            ConfigKind::EngineSettings => 'EngineSettingsVersion is added in sprint 2 task 03.',
        });
    }

    /**
     * @return class-string<ConfigDocument>
     */
    public static function documentClass(ConfigKind $kind): string
    {
        return self::$documents[$kind->value] ?? throw new LogicException(match ($kind) {
            ConfigKind::Rates => 'RatesDocument is added in sprint 2 task 02.',
            ConfigKind::BusinessRules => 'BusinessRulesDocument is added in sprint 2 task 04.',
            ConfigKind::EngineSettings => 'EngineSettingsDocument is added in sprint 2 task 03.',
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function initialDocument(ConfigKind $kind): ?array
    {
        return self::$initials[$kind->value] ?? null;
    }
}
