<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Agency;
use App\Models\User;

final class AgencyPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelRms);
    }

    public function view(User $actor, Agency $agency): bool
    {
        return $actor->hasPermission(Permission::PanelRms);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::AgenciesManage);
    }

    public function update(User $actor, Agency $agency): bool
    {
        return $actor->hasPermission(Permission::AgenciesManage);
    }

    public function decide(User $actor, Agency $agency): bool
    {
        return $actor->hasPermission(Permission::AgenciesManage);
    }

    public function manageUsers(User $actor, Agency $agency): bool
    {
        return $actor->hasPermission(Permission::AgenciesManage);
    }

    public function viewPortalPreview(User $actor, Agency $agency): bool
    {
        return $actor->hasPermission(Permission::AgenciesManage);
    }

    public function managePortalAccess(User $actor, Agency $agency): bool
    {
        return $actor->hasPermission(Permission::AgenciesManage);
    }
}
