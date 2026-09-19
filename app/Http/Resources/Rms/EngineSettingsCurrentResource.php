<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Support\Config\Documents\EngineSettingsDocument;
use Illuminate\Http\Request;

class EngineSettingsCurrentResource extends ConfigCurrentResource
{
    /**
     * @return array{
     *     version: int,
     *     document: array<string, mixed>,
     *     published_at: string,
     *     published_by: array{id: int, name: string}|null,
     *     approval_reference: string|null,
     *     copy_paths: list<string>
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'copy_paths' => EngineSettingsDocument::copyPaths(),
        ];
    }
}
