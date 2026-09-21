<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Enums\ConfigKind;

class ExtrasController extends ConfigController
{
    protected function kind(): ConfigKind
    {
        return ConfigKind::Extras;
    }
}
