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

        if (is_array($document['nps'] ?? null)
            && array_key_exists('survey_hours_after_return', $document['nps'])
            && array_key_exists('alert_below', $document['nps'])
            && array_key_exists('review_request_from', $document['nps'])
            && array_key_exists('review_url', $document['nps'])) {
            return;
        }

        $document['nps'] = [
            'survey_hours_after_return' => 24,
            'alert_below' => 7,
            'review_request_from' => 8,
            'review_url' => 'PENDING CLIENT',
        ];

        app(ConfigPublisher::class)->publish(
            ConfigKind::BusinessRules,
            $document,
            $current->version,
            'Sprint 11: nps.* added (defaults 24 / 7 / 8 / PENDING CLIENT, source N8, review URL PENDING CLIENT)',
            null,
        );
    }

    public function down(): void
    {
        // Versions are append-only.
    }
};
