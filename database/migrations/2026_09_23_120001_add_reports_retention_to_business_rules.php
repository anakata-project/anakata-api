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

        if (is_array($document['reports'] ?? null) && array_key_exists('retention_days', $document['reports'])) {
            return;
        }

        $document['reports'] = [
            'retention_days' => 90,
        ];

        app(ConfigPublisher::class)->publish(
            ConfigKind::BusinessRules,
            $document,
            $current->version,
            'Sprint 12: reports.retention_days added (default 90, source O2, PENDING CLIENT)',
            null,
        );
    }

    public function down(): void
    {
        // Versions are append-only.
    }
};
