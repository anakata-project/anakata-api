<?php

declare(strict_types=1);

namespace App\Support\Roles;

use App\Enums\Permission;
use App\Models\Role;
use App\Support\History\History;
use Illuminate\Support\Facades\DB;

final class GrantEngineCopyManage
{
    public const REASON = 'Sprint 2: new permission engine_copy.manage';

    public static function grant(): void
    {
        DB::transaction(function (): void {
            $role = Role::query()->where('slug', 'manager')->lockForUpdate()->first();

            if (! $role instanceof Role) {
                return;
            }

            if ($role->permissions->contains(Permission::EngineCopyManage)) {
                return;
            }

            $before = self::sortedValues($role);
            $role->permissions = $role->permissions->push(Permission::EngineCopyManage)->unique()->values();
            $role->save();
            $after = self::sortedValues($role);

            History::record(
                $role,
                'role.updated',
                before: ['permissions' => $before],
                after: [
                    'permissions' => $after,
                    'added' => [Permission::EngineCopyManage->value],
                    'removed' => [],
                ],
                reason: self::REASON,
                actor: null,
            );
        });
    }

    public static function revoke(): void
    {
        $role = Role::query()->where('slug', 'manager')->first();

        if (! $role instanceof Role) {
            return;
        }

        if (! $role->permissions->contains(Permission::EngineCopyManage)) {
            return;
        }

        $kept = array_values(array_filter(
            $role->permissions->all(),
            fn (Permission $permission): bool => $permission !== Permission::EngineCopyManage,
        ));
        $role->setAttribute('permissions', $kept);
        $role->save();
    }

    /**
     * @return list<string>
     */
    private static function sortedValues(Role $role): array
    {
        return $role->permissions
            ->map(fn (Permission $permission): string => $permission->value)
            ->sort()
            ->values()
            ->all();
    }
}
