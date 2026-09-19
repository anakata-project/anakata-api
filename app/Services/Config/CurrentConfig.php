<?php

declare(strict_types=1);

namespace App\Services\Config;

use App\Enums\ConfigKind;
use App\Models\ConfigVersion;
use App\Support\Config\ConfigDocument;
use App\Support\Config\Documents\BusinessRulesDocument;
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

    public function has(ConfigKind $kind): bool
    {
        if (isset($this->memo[$kind->value])) {
            return true;
        }

        $modelClass = $kind->modelClass();

        return $modelClass::query()->exists();
    }

    public function version(ConfigKind $kind): ConfigVersion
    {
        if (isset($this->memo[$kind->value])) {
            return $this->memo[$kind->value];
        }

        $modelClass = $kind->modelClass();

        // Cache the row id, not the Eloquent model. Laravel 13 sets
        // cache.serializable_classes to false, so a cached model comes back as
        // __PHP_Incomplete_Class and GET /api/rms/{kind} 500s after the first hit.
        $id = Cache::rememberForever(
            $kind->cacheKey(),
            function () use ($kind, $modelClass): int {
                $row = $modelClass::query()->orderByDesc('version')->first();

                if (! $row instanceof ConfigVersion) {
                    throw new RuntimeException('No published '.$kind->label().' — run the seeders');
                }

                return (int) $row->id;
            },
        );

        if (! is_int($id)) {
            Cache::forget($kind->cacheKey());

            return $this->version($kind);
        }

        $row = $modelClass::query()->find($id);

        if (! $row instanceof ConfigVersion) {
            Cache::forget($kind->cacheKey());

            return $this->version($kind);
        }

        return $this->memo[$kind->value] = $row;
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

    public function businessRules(): BusinessRulesDocument
    {
        $document = $this->document(ConfigKind::BusinessRules);

        if (! $document instanceof BusinessRulesDocument) {
            throw new RuntimeException('Published business rules are not a BusinessRulesDocument.');
        }

        return $document;
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
