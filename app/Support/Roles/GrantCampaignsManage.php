<?php

declare(strict_types=1);

namespace App\Support\Roles;

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Support\History\History;
use Illuminate\Support\Facades\DB;

final class GrantCampaignsManage
{
    public const REASON = 'Sprint 10: Manager may manage CRM campaigns';

    public static function grant(): void
    {
        DB::transaction(function (): void {
            $role = Role::query()->where('slug', SystemRole::Manager->value)->lockForUpdate()->first();

            if (! $role instanceof Role) {
                return;
            }

            if ($role->permissions->contains(Permission::CampaignsManage)) {
                return;
            }

            $before = self::sortedValues($role);
            $role->permissions = $role->permissions->push(Permission::CampaignsManage)->unique()->values();
            $role->save();
            $after = self::sortedValues($role);

            History::record(
                $role,
                'role.updated',
                before: ['permissions' => $before],
                after: [
                    'permissions' => $after,
                    'added' => [Permission::CampaignsManage->value],
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
