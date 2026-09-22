<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Manifest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class ManifestPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelRms);
    }

    public function view(User $actor, Manifest $manifest): bool
    {
        return $actor->hasPermission(Permission::PanelRms);
    }

    public function generate(User $actor): Response
    {
        return $actor->hasPermission(Permission::GuestsViewSensitive)
            ? Response::allow()
            : Response::deny();
    }

    public function download(User $actor, Manifest $manifest): Response
    {
        return $actor->hasPermission(Permission::GuestsViewSensitive)
            ? Response::allow()
            : Response::deny();
    }
}
