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

        if (array_key_exists('analytics', $versions)) {
            return;
        }

        $versions['analytics'] = 'v1 (pending LEG-002)';
        $legal['consent_versions'] = $versions;
        $document['legal'] = $legal;

        // Later Sprint 10 migrations add these. rules() already requires them, so a
        // database published before those migrations cannot be republished without them.
        $crm = is_array($document['crm'] ?? null) ? $document['crm'] : [];
        $pipeline = is_array($crm['pipeline'] ?? null) ? $crm['pipeline'] : [];
        $pipelineDefaults = [
            'sla_new_lead_business_hours' => 4,
            'sla_qualifying_business_days' => 5,
            'sla_negotiation_business_days' => 7,
            'probability_new_lead' => 5,
            'probability_qualifying' => 15,
            'probability_quoted' => 35,
            'probability_negotiation' => 55,
            'probability_deposit_pending' => 80,
        ];

        foreach ($pipelineDefaults as $key => $value) {
            if (! array_key_exists($key, $pipeline)) {
                $pipeline[$key] = $value;
            }
        }

        $crm['pipeline'] = $pipeline;
        $document['crm'] = $crm;

        $privacy = is_array($document['privacy'] ?? null) ? $document['privacy'] : [];

        if (! array_key_exists('request_sla_days', $privacy)) {
            $privacy['request_sla_days'] = 30;
        }

        $document['privacy'] = $privacy;

        app(ConfigPublisher::class)->publish(
            ConfigKind::BusinessRules,
            $document,
            $current->version,
            'Sprint 10: legal.consent_versions.analytics added (default v1 (pending LEG-002), source LEG-002)',
            null,
        );
    }

    public function down(): void
    {
        // Versions are append-only.
    }
};
