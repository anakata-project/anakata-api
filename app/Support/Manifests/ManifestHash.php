<?php

declare(strict_types=1);

namespace App\Support\Manifests;

use App\Enums\ManifestKind;
use Illuminate\Support\Collection;

final class ManifestHash
{
    /**
     * @param  Collection<int, ManifestPassenger>  $passengers
     */
    public static function of(ManifestKind $kind, Collection $passengers): string
    {
        $payload = $passengers
            ->map(fn (ManifestPassenger $passenger): array => $passenger->hashPayload($kind))
            ->values()
            ->all();

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
