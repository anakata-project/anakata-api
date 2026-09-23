<?php

declare(strict_types=1);

namespace App\Actions\Agencies;

use App\Actions\Action;
use App\Enums\AgencyStatus;
use App\Enums\AgencyUserStatus;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Validation\ValidationException;

final class DecideAgency extends Action
{
    public function __construct(private InviteAgencyUser $inviteAgencyUser) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Agency $agency, array $data, User $actor): Agency
    {
        return $this->transaction(function () use ($agency, $data, $actor): Agency {
            if ($agency->status !== AgencyStatus::Pending) {
                throw ValidationException::withMessages([
                    'decision' => ['This agency has already been decided.'],
                ]);
            }

            $decision = $data['decision'] instanceof AgencyStatus
                ? $data['decision']
                : AgencyStatus::from((string) $data['decision']);

            if (! in_array($decision, [AgencyStatus::Approved, AgencyStatus::Rejected], true)) {
                throw ValidationException::withMessages([
                    'decision' => ['Decision must be APPROVED or REJECTED.'],
                ]);
            }

            $reason = isset($data['reason']) ? trim((string) $data['reason']) : '';

            if ($decision === AgencyStatus::Rejected && $reason === '') {
                throw ValidationException::withMessages([
                    'reason' => ['A reason is required when rejecting an agency.'],
                ]);
            }

            $agency->status = $decision;
            $agency->decided_at = now();
            $agency->decided_by = $actor->id;
            $agency->decision_reason = $reason !== '' ? $reason : null;
            $agency->save();

            $usersBefore = $agency->users->map(fn (AgencyUser $user): array => [
                'id' => $user->id,
                'status' => $user->status->value,
            ])->values()->all();

            $toInvite = $agency->users->filter(
                fn (AgencyUser $user): bool => in_array($user->status, [
                    AgencyUserStatus::InviteOnApproval,
                    AgencyUserStatus::InviteOnPortalLaunch,
                ], true),
            );

            if ($decision === AgencyStatus::Approved) {
                $agency->users()->update(['status' => AgencyUserStatus::InviteOnPortalLaunch->value]);

                foreach ($toInvite as $user) {
                    $this->inviteAgencyUser->handle($user->fresh() ?? $user, $actor);
                }
            }

            $event = $decision === AgencyStatus::Approved ? 'agency.approved' : 'agency.rejected';
            $usersAfter = $decision === AgencyStatus::Approved
                ? array_map(
                    fn (array $user): array => [
                        'id' => $user['id'],
                        'status' => AgencyUserStatus::InviteOnPortalLaunch->value,
                    ],
                    $usersBefore,
                )
                : $usersBefore;

            History::record($agency, $event, before: [
                'status' => AgencyStatus::Pending->value,
                'users' => $usersBefore,
            ], after: [
                'status' => $decision->value,
                'users' => $usersAfter,
            ], reason: $reason !== '' ? $reason : null, actor: $actor);

            return $agency->fresh(['users', 'decidedBy', 'bookings']) ?? $agency;
        });
    }
}
