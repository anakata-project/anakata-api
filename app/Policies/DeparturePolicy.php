<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Departure;
use App\Models\User;

final class DeparturePolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelRms);
    }

    public function view(User $actor, Departure $departure): bool
    {
        return $actor->hasPermission(Permission::PanelRms);
    }

    public function viewHistory(User $actor, Departure $departure): bool
    {
        return $actor->hasPermission(Permission::PanelRms);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::DeparturesManage);
    }

    public function update(User $actor, Departure $departure): bool
    {
        return $actor->hasPermission(Permission::DeparturesManage);
    }

    public function delete(User $actor, Departure $departure): bool
    {
        return $actor->hasPermission(Permission::DeparturesManage);
    }

    public function generate(User $actor): bool
    {
        return $actor->hasPermission(Permission::DeparturesManage);
    }
}
