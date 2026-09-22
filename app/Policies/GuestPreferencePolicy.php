<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

final class GuestPreferencePolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelRms);
    }

    public function record(User $actor): bool
    {
        return $actor->hasPermission(Permission::GuestExperienceManage);
    }

    public function viewSensitive(User $actor): bool
    {
        return $actor->hasPermission(Permission::GuestsViewSensitive);
    }
}
