<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Actions\Action;
use App\Exceptions\ConflictException;
use App\Models\Role;
use App\Support\History\History;

final class DeleteRole extends Action
{
    public function handle(Role $role): void
    {
        if ($role->isAdmin()) {
            throw new ConflictException('The Admin role cannot be deleted.');
        }

        if ($role->is_system) {
            throw new ConflictException('A system role cannot be deleted.');
        }

        $this->transaction(function () use ($role): void {
            $usersCount = $role->users()->lockForUpdate()->count();

            if ($usersCount > 0) {
                throw new ConflictException("This role still has {$usersCount} users.");
            }

            History::record($role, 'role.deleted');

            $role->delete();
        });
    }
}
