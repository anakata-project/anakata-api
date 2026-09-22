<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

final class GuestResponsePolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::GuestExperienceManage);
    }

    public function record(User $actor): bool
    {
        return $actor->hasPermission(Permission::GuestExperienceManage);
    }
}
