<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CrmTask;
use App\Models\User;

final class CrmTaskPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function view(User $actor, CrmTask $task): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::ContactsManage);
    }
}
