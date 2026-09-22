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
        $legal = is_array($document['legal'] ?? null) ? $document['legal'] : [];
        $versions = is_array($legal['consent_versions'] ?? null) ? $legal['consent_versions'] : [];

        $defaults = [
            'terms' => 'v2026.1 (text pending LEG-001)',
            'cancellation' => 'v2026.1 (pending LEG-001)',
            'privacy' => 'v2026.1 (pending LEG-002)',
            'insurance' => 'OPS-005 v1',
            'marketing' => 'v1',
            'analytics' => 'v1 (pending LEG-002)',
        ];

        $added = false;

        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $versions)) {
                $versions[$key] = $value;
                $added = true;
            }
        }

        if (! $added) {
            return;
        }

        $legal['consent_versions'] = $versions;
        $document['legal'] = $legal;

        app(ConfigPublisher::class)->publish(
            ConfigKind::BusinessRules,
            $document,
            $current->version,
            'Sprint 6: legal.consent_versions added (defaults from prototype CONSENT_VER, sources LEG-001 / LEG-002 / OPS-005)',
            null,
        );
    }

    public function down(): void
    {
        // Versions are append-only.
    }
};
