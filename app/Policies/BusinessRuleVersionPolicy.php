<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;

final class BusinessRuleVersionPolicy extends ConfigPolicy
{
    protected function viewPermission(): Permission
    {
        return Permission::RulesView;
    }

    protected function publishPermission(): Permission
    {
        return Permission::RulesManage;
    }
}
