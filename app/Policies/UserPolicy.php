<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\ChecksAssignableRole;

final class UserPolicy extends Policy
{
    use ChecksAssignableRole;

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::UsersManage);
    }

    public function viewHistory(User $actor, User $target): bool
    {
        return $actor->hasPermission(Permission::UsersManage);
    }

    public function create(User $actor, Role $role): bool
    {
        return $actor->hasPermission(Permission::UsersManage)
            && $this->isAssignable($actor, $role);
    }

    public function update(User $actor, User $target, ?Role $newRole = null): bool
    {
        if (! $actor->hasPermission(Permission::UsersManage)) {
            return false;
        }

        if (! $this->targetRoleIsAssignable($actor, $target)) {
            return false;
        }

        if ($newRole instanceof Role && ! $this->isAssignable($actor, $newRole)) {
            return false;
        }

        return true;
    }

    public function disable(User $actor, User $target): bool
    {
        return $actor->hasPermission(Permission::UsersManage)
            && $this->targetRoleIsAssignable($actor, $target);
    }

    public function enable(User $actor, User $target): bool
    {
        return $actor->hasPermission(Permission::UsersManage)
            && $this->targetRoleIsAssignable($actor, $target);
    }

    public function resendInvitation(User $actor, User $target): bool
    {
        return $actor->hasPermission(Permission::UsersManage)
            && $this->targetRoleIsAssignable($actor, $target);
    }
}
