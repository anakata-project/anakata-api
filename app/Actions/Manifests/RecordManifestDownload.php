<?php

declare(strict_types=1);

namespace App\Actions\Manifests;

use App\Actions\Action;
use App\Enums\ManifestFormat;
use App\Models\Manifest;
use App\Models\User;
use App\Support\History\History;
use App\Support\Iso;

final class RecordManifestDownload extends Action
{
    public function handle(Manifest $manifest, ManifestFormat $format, User $actor): void
    {
        $this->transaction(function () use ($manifest, $format, $actor): void {
            History::record($manifest, 'manifest.downloaded', after: [
                'kind' => $manifest->kind->value,
                'version' => $manifest->version,
                'format' => $format->value,
                'at' => Iso::utc(now()),
            ], actor: $actor, extraContext: [
                'what' => $manifest->kind->label().' v'.$manifest->version.' downloaded ('.$format->value.')',
            ]);
        });
    }
}
