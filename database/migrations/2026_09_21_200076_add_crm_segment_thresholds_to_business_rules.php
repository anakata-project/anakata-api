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
        $crm = is_array($document['crm'] ?? null) ? $document['crm'] : [];

        $defaults = [
            'segment_high_ltv' => 20000,
            'segment_mid_ltv' => 8000,
        ];

        $added = false;

        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $crm)) {
                $crm[$key] = $value;
                $added = true;
            }
        }

        if (! $added) {
            return;
        }

        $document['crm'] = $crm;

        app(ConfigPublisher::class)->publish(
            ConfigKind::BusinessRules,
            $document,
            $current->version,
            'Sprint 9: crm.segment_high_ltv (20000) and crm.segment_mid_ltv (8000) added (PENDING CLIENT, source L2 / prototype segOf)',
            null,
        );
    }

    public function down(): void
    {
        // Versions are append-only.
    }
};
