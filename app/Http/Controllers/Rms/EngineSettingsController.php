<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Enums\ConfigKind;
use App\Http\Requests\Rms\ValidateConfigRequest;
use App\Http\Resources\Rms\EngineSettingsCurrentResource;
use App\Http\Resources\Rms\EngineSettingsValidationResource;
use App\Models\EngineSettingsVersion;
use App\Services\Config\ConfigValidator;
use App\Services\Config\CurrentConfig;
use App\Support\Config\DocumentDiff;
use App\Support\Config\Documents\EngineSettingsDocument;

class EngineSettingsController extends ConfigController
{
    protected function kind(): ConfigKind
    {
        return ConfigKind::EngineSettings;
    }

    public function current(CurrentConfig $current): EngineSettingsCurrentResource
    {
        $this->authorizeView();

        $version = $current->version($this->kind())->load('publisher');

        return new EngineSettingsCurrentResource($version);
    }

    public function validateDocument(
        ValidateConfigRequest $request,
        ConfigValidator $validator,
    ): EngineSettingsValidationResource {
        $this->authorizeView();

        /** @var array<string, mixed> $document */
        $document = $request->validated('document');

        return new EngineSettingsValidationResource($validator->check($this->kind(), $document));
    }

    /**
     * @param  array<string, mixed>  $document
     */
    protected function authorizePublish(array $document): void
    {
        $current = EngineSettingsVersion::query()->orderByDesc('version')->first();
        $published = $current instanceof EngineSettingsVersion
            ? $current->asDocument()->toArray()
            : [];

        $changes = DocumentDiff::compare($published, $document, EngineSettingsDocument::labels());
        $paths = [];

        foreach ($changes as $change) {
            $paths[] = $change->path;
        }

        $this->authorize('publish', [EngineSettingsVersion::class, $paths]);
    }
}
