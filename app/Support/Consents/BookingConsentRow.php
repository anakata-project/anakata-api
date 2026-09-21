<?php

declare(strict_types=1);

namespace App\Support\Consents;

use App\Enums\ConsentDocument;
use App\Models\Consent;
use App\Support\Config\Documents\ConsentVersions;

final readonly class BookingConsentRow
{
    public function __construct(
        public ConsentDocument $document,
        public ConsentVersions $versions,
        public ?Consent $consent,
    ) {}
}
