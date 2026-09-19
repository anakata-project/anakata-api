<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;

final class RateVersionPolicy extends ConfigPolicy
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
