<?php

declare(strict_types=1);

namespace App\Actions\Agencies;

use App\Actions\Action;
use App\Models\Agency;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Validation\ValidationException;

final class ResumeAgencyPortal extends Action
{
    public function handle(Agency $agency, string $reason, User $actor): Agency
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['A reason is required to resume portal access.'],
            ]);
        }

        return $this->transaction(function () use ($agency, $reason, $actor): Agency {
            if (! $agency->isPortalSuspended()) {
                return $agency;
            }

            $suspendedAt = $agency->portal_suspended_at;

            $agency->portal_suspended_at = null;
            $agency->portal_suspended_by = null;
            $agency->portal_suspend_reason = null;
            $agency->save();

            History::record($agency, 'agency.portal_resumed', before: [
                'portal_suspended_at' => $suspendedAt?->toIso8601String(),
            ], after: [
                'portal_suspended_at' => null,
            ], reason: $reason, actor: $actor);

            return $agency->fresh(['users', 'decidedBy', 'bookings']) ?? $agency;
        });
    }
}
