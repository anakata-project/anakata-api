<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\ConfigKind;
use App\Models\ConfigVersion;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class ConfigPublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public ConfigKind $kind,
        public ConfigVersion $version,
    ) {}
}
