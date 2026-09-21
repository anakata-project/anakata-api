<?php

declare(strict_types=1);

use App\Enums\ConfigKind;
use App\Models\BusinessRuleVersion;
use App\Services\Config\ConfigPublisher;
use Illuminate\Database\Migrations\Migration;

/**
 * DML only — no Schema:: calls. The feature test re-runs up() inside RefreshDatabase.
 *
 * Defaults are hard-coded so this stays reproducible if BusinessRulesDocument::initial() changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $current = BusinessRuleVersion::query()->orderByDesc('version')->first();

        if (! $current instanceof BusinessRuleVersion) {
            return;
        }

        $document = $current->document;
        $retention = is_array($document['retention'] ?? null) ? $document['retention'] : [];

        $defaults = [
            'behavioural_raw_months' => 24,
            'behavioural_unstitched_days' => 30,
        ];

        $added = false;

        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $retention)) {
                $retention[$key] = $value;
                $added = true;
            }
        }

        if (! $added) {
            return;
        }

        $document['retention'] = $retention;

        app(ConfigPublisher::class)->publish(
            ConfigKind::BusinessRules,
            $document,
            $current->version,
            'Sprint 9: retention.behavioural_raw_months (24) and retention.behavioural_unstitched_days (30) added (PENDING CLIENT, source L6 / doc 07 §8, LEG-002)',
            null,
        );
    }

    public function down(): void
    {
        // Versions are append-only.
    }
};
