<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

trait ChecksAssignableRole
{
    protected function canBypassEscalation(User $actor): bool
    {
        $actor->loadMissing('role');

        return $actor->role->isAdmin();
    }

    protected function isAssignable(User $actor, Role $role): bool
    {
        if ($this->canBypassEscalation($actor)) {
            return true;
        }

        if ($role->isAdmin()) {
            return false;
        }

        $held = $actor->permissions();

        return $role->permissions->every(
            fn (Permission $permission): bool => $held->contains($permission),
        );
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     */
    protected function permissionsAreHeld(User $actor, Collection $permissions): bool
    {
        if ($this->canBypassEscalation($actor)) {
            return true;
        }

        $held = $actor->permissions();

        return $permissions->every(
            fn (Permission $permission): bool => $held->contains($permission),
        );
    }

    protected function targetRoleIsAssignable(User $actor, User $target): bool
    {
        $target->loadMissing('role');

        return $this->isAssignable($actor, $target->role);
    }
}
