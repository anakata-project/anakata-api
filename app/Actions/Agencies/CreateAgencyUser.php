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

final class CreateAgencyUser extends Action
{
    /**
     * @param  array{name: string, email: string}  $data
     */
    public function handle(Agency $agency, array $data, User $actor): Agency
    {
        return $this->transaction(function () use ($agency, $data, $actor): Agency {
            $status = $agency->status === AgencyStatus::Approved
                ? AgencyUserStatus::InviteOnPortalLaunch
                : AgencyUserStatus::InviteOnApproval;

            $user = AgencyUser::query()->create([
                'agency_id' => $agency->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'status' => $status,
            ]);

            History::record($agency, 'agency.user_created', after: [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status->value,
            ], actor: $actor);

            return $agency->fresh(['users', 'decidedBy', 'bookings']) ?? $agency;
        });
    }
}
