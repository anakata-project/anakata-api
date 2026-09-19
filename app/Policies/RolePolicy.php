<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\ChecksAssignableRole;
use Illuminate\Support\Collection;

final class RolePolicy extends Policy
{
    use ChecksAssignableRole;

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::UsersManage)
            || $actor->hasPermission(Permission::RolesManage);
    }

    public function viewHistory(User $actor, Role $role): bool
    {
        return $actor->hasPermission(Permission::RolesManage);
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     */
    public function create(User $actor, Collection $permissions): bool
    {
        return $actor->hasPermission(Permission::RolesManage)
            && $this->permissionsAreHeld($actor, $permissions);
    }

    /**
     * @param  Collection<int, Permission>  $added
     */
    public function update(User $actor, Role $role, Collection $added): bool
    {
        if (! $actor->hasPermission(Permission::RolesManage)) {
            return false;
        }

        if (! $this->isAssignable($actor, $role)) {
            return false;
        }

        if (! $this->canBypassEscalation($actor) && $actor->role_id === $role->id) {
            return false;
        }

        return $this->permissionsAreHeld($actor, $added);
    }

    public function delete(User $actor, Role $role): bool
    {
        return $actor->hasPermission(Permission::RolesManage)
            && $this->isAssignable($actor, $role);
    }
}
