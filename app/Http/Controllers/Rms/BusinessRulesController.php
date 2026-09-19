<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rms;

use App\Enums\ConfigKind;
use App\Http\Resources\Rms\BusinessRulesCurrentResource;
use App\Services\Config\CurrentConfig;

class BusinessRulesController extends ConfigController
{
    protected function kind(): ConfigKind
    {
        return ConfigKind::BusinessRules;
    }

    public function current(CurrentConfig $current): BusinessRulesCurrentResource
    {
        $this->authorizeView();

        $version = $current->version($this->kind())->load('publisher');

        return new BusinessRulesCurrentResource($version);
    }
}
