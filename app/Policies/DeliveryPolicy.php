<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

final class DeliveryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PanelCrm);
    }
}
