<?php

declare(strict_types=1);

namespace App\Actions\Agencies;

use App\Actions\Action;
use App\Enums\AgencyUserStatus;
use App\Models\Agency;
use App\Models\AgencyUser;
use App\Models\User;
use App\Support\History\History;

final class UpdateAgencyUser extends Action
{
    /**
     * @param  array{name?: string, status?: string}  $data
     */
    public function handle(Agency $agency, AgencyUser $user, array $data, User $actor): Agency
    {
        return $this->transaction(function () use ($agency, $user, $data, $actor): Agency {
            $before = [
                'name' => $user->name,
                'status' => $user->status->value,
            ];

            if (array_key_exists('name', $data)) {
                $user->name = $data['name'];
            }

            if (array_key_exists('status', $data)) {
                $user->status = AgencyUserStatus::from($data['status']);
            }

            $after = [
                'name' => $user->name,
                'status' => $user->status->value,
            ];

            if ($before === $after) {
                return $agency->fresh(['users', 'decidedBy', 'bookings']) ?? $agency;
            }

            $user->save();

            History::record($agency, 'agency.user_updated', before: [
                'user_id' => $user->id,
                ...$before,
            ], after: [
                'user_id' => $user->id,
                ...$after,
            ], actor: $actor);

            return $agency->fresh(['users', 'decidedBy', 'bookings']) ?? $agency;
        });
    }
}
