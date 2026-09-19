<?php

declare(strict_types=1);

namespace App\Services\Config;

use App\Enums\ConfigKind;
use App\Models\ConfigVersion;
use App\Support\Config\ConfigDocument;
use App\Support\Config\Documents\EngineSettingsDocument;
use App\Support\Config\Documents\RatesDocument;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class CurrentConfig
{
    /**
     * @var array<string, ConfigVersion>
     */
    private array $memo = [];

    public function version(ConfigKind $kind): ConfigVersion
    {
        if (isset($this->memo[$kind->value])) {
            return $this->memo[$kind->value];
        }

        $version = Cache::rememberForever(
            $kind->cacheKey(),
            function () use ($kind): ConfigVersion {
                $modelClass = $kind->modelClass();
                $row = $modelClass::query()->orderByDesc('version')->first();

                if (! $row instanceof ConfigVersion) {
                    throw new RuntimeException('No published '.$kind->label().' — run the seeders');
                }

                return $row;
            },
        );

        return $this->memo[$kind->value] = $version;
    }

    public function document(ConfigKind $kind): ConfigDocument
    {
        return $this->version($kind)->asDocument();
    }

    public function rates(): RatesDocument
    {
        $document = $this->document(ConfigKind::Rates);

        if (! $document instanceof RatesDocument) {
            throw new RuntimeException('Published rates are not a RatesDocument.');
        }

        return $document;
    }

    public function businessRules(): ConfigDocument
    {
        return $this->document(ConfigKind::BusinessRules);
    }

    public function engineSettings(): EngineSettingsDocument
    {
        $document = $this->document(ConfigKind::EngineSettings);

        if (! $document instanceof EngineSettingsDocument) {
            throw new RuntimeException('Published engine settings are not an EngineSettingsDocument.');
        }

        return $document;
    }

    public function forget(ConfigKind $kind): void
    {
        unset($this->memo[$kind->value]);
    }
}
