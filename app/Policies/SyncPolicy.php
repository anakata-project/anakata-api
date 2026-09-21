<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

final class SyncPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function retry(User $actor): bool
    {
        return $actor->hasPermission(Permission::SyncRetry);
    }
}
