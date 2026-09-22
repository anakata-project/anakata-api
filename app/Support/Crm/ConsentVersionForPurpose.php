<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\ConsentPurpose;
use App\Services\Config\CurrentConfig;

final class ConsentVersionForPurpose
{
    public static function current(ConsentPurpose $purpose): ?string
    {
        $versions = app(CurrentConfig::class)->businessRules()->consentVersions;

        $version = match ($purpose) {
            ConsentPurpose::Marketing => $versions->marketing,
            ConsentPurpose::Analytics => $versions->analytics,
            ConsentPurpose::Profiling,
            ConsentPurpose::Remarketing,
            ConsentPurpose::Whatsapp => '',
        };

        $version = trim($version);

        return $version === '' ? null : $version;
    }
}
