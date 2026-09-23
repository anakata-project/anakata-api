<?php

declare(strict_types=1);

use App\Enums\ConfigKind;
use App\Models\BusinessRuleVersion;
use App\Services\Config\ConfigPublisher;
use Illuminate\Database\Migrations\Migration;

/**
 * DML only — no Schema:: calls. The feature test re-runs up() inside RefreshDatabase.
 *
 * The default is hard-coded so this stays reproducible if BusinessRulesDocument::initial() changes.
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

        if (array_key_exists('checkout_marketing', $versions)) {
            return;
        }

        $versions['checkout_marketing'] = 'v1 (pending LEG-002)';
        $legal['consent_versions'] = $versions;
        $document['legal'] = $legal;

        app(ConfigPublisher::class)->publish(
            ConfigKind::BusinessRules,
            $document,
            $current->version,
            'Sprint 14: legal.consent_versions.checkout_marketing added (default v1 (pending LEG-002), source LEG-002)',
            null,
        );
    }

    public function down(): void
    {
        // Versions are append-only.
    }
};
