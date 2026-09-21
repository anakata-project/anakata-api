<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;

final class PaymentPolicy extends Policy
{
    public function viewOptions(User $actor): bool
    {
        return $actor->hasPermission(Permission::PanelRms);
    }

    public function markReceived(User $actor, Payment $payment): bool
    {
        return $actor->hasPermission(Permission::PaymentsMarkWireReceived);
    }

    public function viewReconciliation(User $actor): bool
    {
        return $actor->hasPermission(Permission::BookingsViewAll);
    }
}
