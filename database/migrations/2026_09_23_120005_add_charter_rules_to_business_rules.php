<?php

declare(strict_types=1);

use App\Enums\ConfigKind;
use App\Models\BusinessRuleVersion;
use App\Services\Config\ConfigPublisher;
use Illuminate\Database\Migrations\Migration;

/**
 * DML only — no Schema:: calls. The feature test re-runs up() inside RefreshDatabase.
 *
 * The defaults are hard-coded so this stays reproducible if BusinessRulesDocument::initial() changes.
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
        $changed = false;

        if (! is_array($document['charter'] ?? null) || ! array_key_exists('deposit_business_days', $document['charter'])) {
            $document['charter'] = [
                'deposit_business_days' => 5,
                'proposal_valid_business_days' => 10,
            ];
            $changed = true;
        }

        $cancellation = is_array($document['cancellation'] ?? null) ? $document['cancellation'] : [];

        if (! array_key_exists('charter_bands', $cancellation)) {
            $cancellation['charter_bands'] = [
                ['min_days' => 120, 'penalty_pct' => 5],
                ['min_days' => 90, 'penalty_pct' => 50],
                ['min_days' => 0, 'penalty_pct' => 100],
            ];
            $document['cancellation'] = $cancellation;
            $changed = true;
        }

        if (! $changed) {
            return;
        }

        app(ConfigPublisher::class)->publish(
            ConfigKind::BusinessRules,
            $document,
            $current->version,
            'Sprint 12: charter.deposit_business_days added (default 5, source FIN-003); charter.proposal_valid_business_days added (default 10, source O5, PENDING CLIENT); cancellation.charter_bands added (default copies cabin bands, source O6, PENDING CLIENT)',
            null,
        );
    }

    public function down(): void
    {
        // Versions are append-only.
    }
};
