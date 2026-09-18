<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SystemRole::cases() as $systemRole) {
            Role::query()->firstOrCreate(
                ['slug' => $systemRole->value],
                [
                    'name' => $systemRole->label(),
                    'description' => null,
                    'permissions' => $systemRole->defaultPermissions(),
                    'is_system' => true,
                ],
            );
        }
    }
}
