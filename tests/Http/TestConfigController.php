<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Enums\ConfigKind;
use App\Http\Controllers\Rms\ConfigController;

final class TestConfigController extends ConfigController
{
    protected function kind(): ConfigKind
    {
        return ConfigKind::Rates;
    }
}
