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
    private array $models = [];

    /**
     * @var array<string, class-string<ConfigDocument>>
     */
    private array $documents = [];

    /**
     * @var array<string, array<string, mixed>|null>
     */
    private array $initials = [];

    /**
     * @param  class-string<ConfigVersion>  $modelClass
     * @param  class-string<ConfigDocument>  $documentClass
     * @param  array<string, mixed>|null  $initialDocument
     */
    public function register(
        ConfigKind $kind,
        string $modelClass,
        string $documentClass,
        ?array $initialDocument = null,
    ): void {
        $this->models[$kind->value] = $modelClass;
        $this->documents[$kind->value] = $documentClass;
        $this->initials[$kind->value] = $initialDocument;
    }

    public function has(ConfigKind $kind): bool
    {
        return isset($this->models[$kind->value], $this->documents[$kind->value]);
    }

    /**
     * @return list<ConfigKind>
     */
    public function kinds(): array
    {
        return array_values(array_filter(
            ConfigKind::cases(),
            $this->has(...),
        ));
    }

    /**
     * @return class-string<ConfigVersion>
     */
    public function modelClass(ConfigKind $kind): string
    {
        return $this->models[$kind->value] ?? throw new LogicException(match ($kind) {
            ConfigKind::Rates => 'RateVersion is added in sprint 2 task 02.',
            ConfigKind::BusinessRules => 'BusinessRuleVersion is added in sprint 2 task 04.',
            ConfigKind::EngineSettings => 'EngineSettingsVersion is added in sprint 2 task 03.',
            ConfigKind::Extras => 'ExtraVersion is added in sprint 6 task 04.',
        });
    }

    /**
     * @return class-string<ConfigDocument>
     */
    public function documentClass(ConfigKind $kind): string
    {
        return $this->documents[$kind->value] ?? throw new LogicException(match ($kind) {
            ConfigKind::Rates => 'RatesDocument is added in sprint 2 task 02.',
            ConfigKind::BusinessRules => 'BusinessRulesDocument is added in sprint 2 task 04.',
            ConfigKind::EngineSettings => 'EngineSettingsDocument is added in sprint 2 task 03.',
            ConfigKind::Extras => 'ExtrasDocument is added in sprint 6 task 04.',
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function initialDocument(ConfigKind $kind): ?array
    {
        return $this->initials[$kind->value] ?? null;
    }
}
