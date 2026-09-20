<?php

declare(strict_types=1);

namespace App\Support\Roles;

use App\Enums\Permission;
use App\Models\Role;
use App\Support\History\History;
use Illuminate\Support\Facades\DB;

final class GrantAgenciesManage
{
    public const REASON = 'Sprint 5: Manager may register and decide agencies';

    public static function grant(): void
    {
        DB::transaction(function (): void {
            $role = Role::query()->where('slug', 'manager')->lockForUpdate()->first();

            if (! $role instanceof Role) {
                return;
            }

            if ($role->permissions->contains(Permission::AgenciesManage)) {
                return;
            }

            $before = self::sortedValues($role);
            $role->permissions = $role->permissions->push(Permission::AgenciesManage)->unique()->values();
            $role->save();
            $after = self::sortedValues($role);

            History::record(
                $role,
                'role.updated',
                before: ['permissions' => $before],
                after: [
                    'permissions' => $after,
                    'added' => [Permission::AgenciesManage->value],
                    'removed' => [],
                ],
                reason: self::REASON,
                actor: null,
            );
        });
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
