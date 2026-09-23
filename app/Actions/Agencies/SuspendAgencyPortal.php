<?php

declare(strict_types=1);

namespace App\Actions\Agencies;

use App\Actions\Action;
use App\Models\Agency;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Validation\ValidationException;

final class SuspendAgencyPortal extends Action
{
    public function handle(Agency $agency, string $reason, User $actor): Agency
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['A reason is required to suspend portal access.'],
            ]);
        }

        return $this->transaction(function () use ($agency, $reason, $actor): Agency {
            if ($agency->isPortalSuspended()) {
                return $agency;
            }

            $agency->portal_suspended_at = now();
            $agency->portal_suspended_by = $actor->id;
            $agency->portal_suspend_reason = $reason;
            $agency->save();

            History::record($agency, 'agency.portal_suspended', before: [
                'portal_suspended_at' => null,
            ], after: [
                'portal_suspended_at' => $agency->portal_suspended_at->toIso8601String(),
            ], reason: $reason, actor: $actor);

            return $agency->fresh(['users', 'decidedBy', 'portalSuspendedBy', 'bookings']) ?? $agency;
        });
    }
}
