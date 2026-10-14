<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([SystemRole::Manager, SystemRole::SalesExec] as $systemRole) {
            $role = Role::query()->where('slug', $systemRole->value)->first();

            if (! $role instanceof Role) {
                continue;
            }

            if ($role->permissions->contains(Permission::CopilotUse)) {
                continue;
            }

            $role->permissions = $role->permissions->push(Permission::CopilotUse)->values();
            $role->save();
        }
    }

    public function down(): void
    {
        foreach ([SystemRole::Manager, SystemRole::SalesExec] as $systemRole) {
            $role = Role::query()->where('slug', $systemRole->value)->first();

            if (! $role instanceof Role) {
                continue;
            }

            $role->permissions = $role->permissions
                ->reject(fn (Permission $permission): bool => $permission === Permission::CopilotUse)
                ->values();
            $role->save();
        }
    }
};
