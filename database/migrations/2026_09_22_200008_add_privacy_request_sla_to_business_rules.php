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
        $privacy = is_array($document['privacy'] ?? null) ? $document['privacy'] : [];

        if (array_key_exists('request_sla_days', $privacy)) {
            return;
        }

        $privacy['request_sla_days'] = 30;
        $document['privacy'] = $privacy;

        app(ConfigPublisher::class)->publish(
            ConfigKind::BusinessRules,
            $document,
            $current->version,
            'Sprint 10: privacy.request_sla_days added (default 30, source M7, PENDING LEG-002)',
            null,
        );
    }

    public function down(): void
    {
        // Versions are append-only.
    }
};
