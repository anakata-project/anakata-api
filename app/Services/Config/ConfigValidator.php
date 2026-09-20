<?php

declare(strict_types=1);

namespace App\Services\Config;

use App\Enums\ConfigKind;
use App\Models\ConfigVersion;
use App\Support\Config\ConfigDocument;
use App\Support\Config\Documents\EngineSettingsDocument;
use App\Support\Config\Documents\RatesDocument;
use App\Support\Config\Warning;
use Illuminate\Support\Facades\Validator;

final class ConfigValidator
{
    public function __construct(private DepartureConfigChecks $departureChecks) {}

    /**
     * @param  array<string, mixed>  $document
     */
    public function check(ConfigKind $kind, array $document): ValidationReport
    {
        $documentClass = $kind->documentClass();
        $validator = Validator::make($document, $documentClass::rules());

        if ($validator->fails()) {
            /** @var array<string, list<string>> $errors */
            $errors = $validator->errors()->toArray();

            return new ValidationReport($errors, [], []);
        }

        $typed = $documentClass::fromArray($document);
        $published = $this->publishedDocument($kind);

        $departureErrors = $this->departureErrors($kind, $typed, $published);

        if ($departureErrors !== []) {
            return new ValidationReport($departureErrors, [], []);
        }

        return new ValidationReport(
            [],
            array_merge($typed->warnings($published), $this->departureWarnings($kind, $typed)),
            $typed->changesAgainst($published),
        );
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function assertValid(ConfigKind $kind, array $document): void
    {
        $prefixed = [];

        foreach ($kind->documentClass()::rules() as $path => $rule) {
            $prefixed['document.'.$path] = $rule;
        }

        Validator::validate(['document' => $document], $prefixed);
    }

    /**
     * @return array<string, list<string>>
     */
    private function departureErrors(ConfigKind $kind, ConfigDocument $typed, ?ConfigDocument $published): array
    {
        if ($kind !== ConfigKind::Rates || ! $typed instanceof RatesDocument) {
            return [];
        }

        return $this->departureChecks->rateYearErrors(
            $typed,
            $published instanceof RatesDocument ? $published : null,
        );
    }

    /**
     * @return list<Warning>
     */
    private function departureWarnings(ConfigKind $kind, ConfigDocument $typed): array
    {
        if ($kind === ConfigKind::Rates && $typed instanceof RatesDocument) {
            return $this->departureChecks->rateYearWarnings($typed);
        }

        if ($kind === ConfigKind::EngineSettings && $typed instanceof EngineSettingsDocument) {
            return $this->departureChecks->engineSearchWarnings($typed);
        }

        return [];
    }

    private function publishedDocument(ConfigKind $kind): ?ConfigDocument
    {
        $modelClass = $kind->modelClass();
        $row = $modelClass::query()->orderByDesc('version')->first();

        if (! $row instanceof ConfigVersion) {
            return null;
        }

        return $row->asDocument();
    }
}
