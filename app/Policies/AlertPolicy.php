<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Alert;
use App\Models\User;
use App\Support\Alerts\AlertRegistry;

final class AlertPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelRms) || $actor->hasPermission(Permission::PanelCrm);
    }

    public function view(User $actor, Alert $alert): bool
    {
        return $this->viewAny($actor) && AlertRegistry::sees($actor, $alert->kind);
    }

    public function acknowledge(User $actor, Alert $alert): bool
    {
        return $this->view($actor, $alert);
    }
}
