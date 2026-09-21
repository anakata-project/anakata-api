<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Offer;
use App\Models\User;

final class OfferPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelRms);
    }

    public function view(User $actor, Offer $offer): bool
    {
        return $actor->hasPermission(Permission::PanelRms);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::OffersManage);
    }

    public function update(User $actor, Offer $offer): bool
    {
        return $actor->hasPermission(Permission::OffersManage);
    }

    public function pause(User $actor, Offer $offer): bool
    {
        return $actor->hasPermission(Permission::OffersManage);
    }

    public function resume(User $actor, Offer $offer): bool
    {
        return $actor->hasPermission(Permission::OffersManage);
    }

    public function approve(User $actor, Offer $offer): bool
    {
        return $actor->hasPermission(Permission::OffersApprove);
    }

    public function reject(User $actor, Offer $offer): bool
    {
        return $actor->hasPermission(Permission::OffersApprove);
    }
}
