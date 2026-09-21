<?php

declare(strict_types=1);

namespace App\Support\Roles;

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Support\History\History;
use Illuminate\Support\Facades\DB;

final class GrantContactsManage
{
    public const REASON = 'Sprint 9: Manager and Sales Exec may manage CRM contacts';

    public static function grant(): void
    {
        foreach ([SystemRole::Manager, SystemRole::SalesExec] as $systemRole) {
            self::grantTo($systemRole);
        }
    }

    private static function grantTo(SystemRole $systemRole): void
    {
        DB::transaction(function () use ($systemRole): void {
            $role = Role::query()->where('slug', $systemRole->value)->lockForUpdate()->first();

            if (! $role instanceof Role) {
                return;
            }

            if ($role->permissions->contains(Permission::ContactsManage)) {
                return;
            }

            $before = self::sortedValues($role);
            $role->permissions = $role->permissions->push(Permission::ContactsManage)->unique()->values();
            $role->save();
            $after = self::sortedValues($role);

            History::record(
                $role,
                'role.updated',
                before: ['permissions' => $before],
                after: [
                    'permissions' => $after,
                    'added' => [Permission::ContactsManage->value],
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
