<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SalesMaterial;
use App\Models\User;

final class SalesMaterialPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::AgenciesManage);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::AgenciesManage);
    }

    public function update(User $actor, SalesMaterial $material): bool
    {
        return $actor->hasPermission(Permission::AgenciesManage);
    }

    public function download(User $actor, SalesMaterial $material): bool
    {
        return $actor->hasPermission(Permission::AgenciesManage);
    }
}
