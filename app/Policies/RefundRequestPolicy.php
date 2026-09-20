<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\RefundRequest;
use App\Models\User;

final class RefundRequestPolicy extends Policy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::RefundsApprove)
            || $actor->hasPermission(Permission::RefundsExecute);
    }

    public function decide(User $actor, RefundRequest $refund): bool
    {
        return $actor->hasPermission(Permission::RefundsApprove);
    }

    public function execute(User $actor, RefundRequest $refund): bool
    {
        return $actor->hasPermission(Permission::RefundsExecute);
    }
}
