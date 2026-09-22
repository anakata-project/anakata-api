<?php

declare(strict_types=1);

namespace App\Support\Manifests;

use App\Models\Departure;
use App\Models\Manifest;

final readonly class ManifestRow
{
    public function __construct(
        public Departure $departure,
        public bool $charter,
        public int $passengers,
        public int $complete,
        public ManifestDue $due,
        public string $status,
        public ?Manifest $dpng,
        public ?Manifest $captain,
    ) {}
}
