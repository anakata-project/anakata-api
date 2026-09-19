<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Actions\Action;
use App\Enums\Permission;
use App\Models\Role;
use App\Support\History\History;
use App\Support\Roles\UniqueRoleSlug;
use Illuminate\Support\Collection;

final class CreateRole extends Action
{
    /**
     * @param  Collection<int, Permission>  $permissions
     */
    public function handle(string $name, ?string $description, Collection $permissions): Role
    {
        return $this->transaction(function () use ($name, $description, $permissions): Role {
            $role = Role::query()->create([
                'name' => $name,
                'slug' => UniqueRoleSlug::fromName($name),
                'description' => $description,
                'permissions' => $permissions,
                'is_system' => false,
            ]);

            History::record($role, 'role.created');

            return $role->loadCount('users');
        });
    }
}
