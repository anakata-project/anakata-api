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
        $documents = is_array($document['documents'] ?? null) ? $document['documents'] : [];

        $defaults = [
            'pretrip_days_before' => 45,
            'voucher_days_before' => 7,
        ];

        $added = false;

        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $documents)) {
                $documents[$key] = $value;
                $added = true;
            }
        }

        if (! $added) {
            return;
        }

        $document['documents'] = $documents;

        app(ConfigPublisher::class)->publish(
            ConfigKind::BusinessRules,
            $document,
            $current->version,
            'Sprint 7: documents.pretrip_days_before (45) and documents.voucher_days_before (7) added (source J7 / prototype T−45 · T−7)',
            null,
        );
    }

    public function down(): void
    {
        // Versions are append-only.
    }
};
