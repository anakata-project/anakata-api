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
        $pipeline = is_array($crm['pipeline'] ?? null) ? $crm['pipeline'] : [];

        $defaults = [
            'sla_new_lead_business_hours' => 4,
            'sla_qualifying_business_days' => 5,
            'sla_negotiation_business_days' => 7,
            'probability_new_lead' => 5,
            'probability_qualifying' => 15,
            'probability_quoted' => 35,
            'probability_negotiation' => 55,
            'probability_deposit_pending' => 80,
        ];

        $added = false;

        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $pipeline)) {
                $pipeline[$key] = $value;
                $added = true;
            }
        }

        if (! $added) {
            return;
        }

        $crm['pipeline'] = $pipeline;
        $document['crm'] = $crm;

        app(ConfigPublisher::class)->publish(
            ConfigKind::BusinessRules,
            $document,
            $current->version,
            'Sprint 10: crm.pipeline SLAs and probabilities added (defaults 4h / 5d / 7d and 5 / 15 / 35 / 55 / 80, source M4, PENDING CLIENT)',
            null,
        );
    }

    public function down(): void
    {
        // Versions are append-only.
    }
};
