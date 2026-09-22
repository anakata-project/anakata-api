<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Deal;
use App\Models\User;

final class DealPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function view(User $actor, Deal $deal): bool
    {
        return $actor->hasPermission(Permission::PanelCrm);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::PipelineMoveStage);
    }

    public function update(User $actor, Deal $deal): bool
    {
        return $actor->hasPermission(Permission::PipelineMoveStage);
    }
}
