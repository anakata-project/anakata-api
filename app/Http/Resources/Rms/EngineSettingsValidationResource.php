<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Services\Config\ValidationReport;
use App\Support\Config\Documents\EngineSettingsDocument;
use Illuminate\Http\Request;

/**
 * @mixin ValidationReport
 */
class EngineSettingsValidationResource extends ConfigValidationResource
{
    /**
     * @return array{
     *     errors: array<string, list<string>>,
     *     warnings: list<array{path: string, message: string}>,
     *     changes: list<array{path: string, label: string, from: mixed, to: mixed}>,
     *     rule_fields_changed: bool
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var ValidationReport $report */
        $report = $this->resource;

        return [
            ...$report->toArray(),
            'rule_fields_changed' => self::ruleFieldsChanged($report),
        ];
    }

    private static function ruleFieldsChanged(ValidationReport $report): bool
    {
        foreach ($report->changes as $change) {
            if (! EngineSettingsDocument::isCopyPath($change->path)) {
                return true;
            }
        }

        return false;
    }
}
