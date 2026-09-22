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
        $manifests = is_array($document['manifests'] ?? null) ? $document['manifests'] : [];
        $changed = false;

        if (! array_key_exists('captain_days', $manifests)) {
            $manifests['captain_days'] = 7;
            $changed = true;
        }

        if (! array_key_exists('chase_days_before_due', $manifests)) {
            $manifests['chase_days_before_due'] = 10;
            $changed = true;
        }

        if (! $changed) {
            return;
        }

        $document['manifests'] = $manifests;

        app(ConfigPublisher::class)->publish(
            ConfigKind::BusinessRules,
            $document,
            $current->version,
            'Sprint 11: manifests.captain_days added (default 7, source N4); manifests.chase_days_before_due added (default 10, source N5, PENDING CLIENT)',
            null,
        );
    }

    public function down(): void
    {
        // Versions are append-only.
    }
};
