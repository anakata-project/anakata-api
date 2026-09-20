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
        $holds = is_array($document['holds'] ?? null) ? $document['holds'] : [];

        $defaults = [
            'business_days' => [1, 2, 3, 4, 5],
            'business_day_start' => '09:00',
            'business_day_end' => '18:00',
            'holidays' => [],
            'near_term_max_days' => 120,
        ];

        $added = false;

        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $holds)) {
                $holds[$key] = $value;
                $added = true;
            }
        }

        if (! $added) {
            return;
        }

        $document['holds'] = $holds;

        app(ConfigPublisher::class)->publish(
            ConfigKind::BusinessRules,
            $document,
            $current->version,
            'Sprint 4: holds business hours added (defaults Mon–Fri 09:00–18:00, near-term ≤ 120 days, source TEC-004 pending client)',
            null,
        );
    }

    public function down(): void
    {
        // Versions are append-only.
    }
};
