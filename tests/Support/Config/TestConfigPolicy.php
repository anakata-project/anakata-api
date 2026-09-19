<?php

declare(strict_types=1);

namespace Tests\Support\Config;

use App\Enums\Permission;
use App\Policies\ConfigPolicy;

final class TestConfigPolicy extends ConfigPolicy
{
    protected function viewPermission(): Permission
    {
        return Permission::PanelRms;
    }

    protected function publishPermission(): Permission
    {
        return Permission::RatesManage;
    }
}
