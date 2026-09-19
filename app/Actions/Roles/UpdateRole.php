<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Actions\Action;
use App\Enums\Permission;
use App\Exceptions\ConflictException;
use App\Models\Role;
use App\Support\History\History;
use Illuminate\Support\Collection;

final class UpdateRole extends Action
{
    /**
     * @param  array{name?: string, description?: string|null, permissions?: Collection<int, Permission>}  $data
     */
    public function handle(Role $role, array $data): Role
    {
        if ($role->isAdmin()) {
            if (array_key_exists('permissions', $data)) {
                throw new ConflictException('The Admin role permissions cannot be edited.');
            }

            if (array_key_exists('name', $data) && $data['name'] !== $role->name) {
                throw new ConflictException('The Admin role cannot be renamed.');
            }
        } elseif ($role->is_system) {
            if (array_key_exists('name', $data) && $data['name'] !== $role->name) {
                throw new ConflictException('A system role cannot be renamed.');
            }
        }

        $nameChanged = array_key_exists('name', $data) && $data['name'] !== $role->name;
        $descriptionChanged = array_key_exists('description', $data)
            && $data['description'] !== $role->description;
        $permissionsChanged = array_key_exists('permissions', $data)
            && ! $this->samePermissions($role->permissions, $data['permissions']);

        if (! $nameChanged && ! $descriptionChanged && ! $permissionsChanged) {
            return $role->loadCount('users');
        }

        return $this->transaction(function () use ($role, $data, $nameChanged, $descriptionChanged, $permissionsChanged): Role {
            $before = [];
            $after = [];

            if ($nameChanged) {
                $before['name'] = $role->name;
                $role->name = $data['name'];
                $after['name'] = $data['name'];
            }

            if ($descriptionChanged) {
                $before['description'] = $role->description;
                $role->description = $data['description'];
                $after['description'] = $data['description'];
            }

            if ($permissionsChanged) {
                $beforePermissions = $this->sortedValues($role->permissions);
                $afterPermissions = $this->sortedValues($data['permissions']);
                $before['permissions'] = $beforePermissions;
                $after['permissions'] = $afterPermissions;
                $after['added'] = array_values(array_diff($afterPermissions, $beforePermissions));
                $after['removed'] = array_values(array_diff($beforePermissions, $afterPermissions));
                $role->permissions = $data['permissions'];
            }

            $role->save();

            History::record($role, 'role.updated', $before, $after);

            return $role->loadCount('users');
        });
    }

    /**
     * @param  Collection<int, Permission>  $left
     * @param  Collection<int, Permission>  $right
     */
    private function samePermissions(Collection $left, Collection $right): bool
    {
        return $this->sortedValues($left) === $this->sortedValues($right);
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return list<string>
     */
    private function sortedValues(Collection $permissions): array
    {
        return $permissions
            ->map(fn (Permission $permission): string => $permission->value)
            ->sort()
            ->values()
            ->all();
    }
}
