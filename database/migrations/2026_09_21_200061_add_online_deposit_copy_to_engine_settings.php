<?php

declare(strict_types=1);

use App\Enums\ConfigKind;
use App\Models\EngineSettingsVersion;
use App\Services\Config\ConfigPublisher;
use Illuminate\Database\Migrations\Migration;

/**
 * DML only — no Schema:: calls. The feature test re-runs up() inside RefreshDatabase.
 *
 * Defaults are hard-coded so this stays reproducible if EngineSettingsDocument::initial() changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $current = EngineSettingsVersion::query()->orderByDesc('version')->first();

        if (! $current instanceof EngineSettingsVersion) {
            return;
        }

        $document = $current->document;
        $copy = is_array($document['copy'] ?? null) ? $document['copy'] : [];

        $defaults = [
            'online_deposit_advantage' => 'Online deposit advantage',
            'online_deposit_perk' => 'Complimentary spa access aboard',
        ];

        $added = false;

        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $copy) || $copy[$key] === '' || $copy[$key] === null) {
                $copy[$key] = $value;
                $added = true;
            }
        }

        if (! $added) {
            return;
        }

        $document['copy'] = $copy;

        app(ConfigPublisher::class)->publish(
            ConfigKind::EngineSettings,
            $document,
            $current->version,
            'Sprint 8: copy.online_deposit_advantage / copy.online_deposit_perk added (defaults from prototype ONLINE_PERK, source B2 / K1)',
            null,
        );
    }

    public function down(): void
    {
        // Versions are append-only.
    }
};
