<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class GuestPolicy extends Policy
{
    public function view(User $actor, Guest $guest): bool
    {
        $guest->loadMissing('booking');

        return app(BookingPolicy::class)->view($actor, $guest->booking);
    }

    public function update(User $actor, Guest $guest): Response
    {
        return $this->authorizeWrite($actor, $guest);
    }

    public function delete(User $actor, Guest $guest): Response
    {
        return $this->authorizeWrite($actor, $guest);
    }

    private function authorizeWrite(User $actor, Guest $guest): Response
    {
        $guest->loadMissing('booking');

        if (! $this->ownsOrMayActOnAny($actor, $guest->booking)) {
            return Response::deny('Blocked: own-records rule.');
        }

        if ($this->writesSensitiveNotes() && ! $actor->hasPermission(Permission::GuestsViewSensitive)) {
            return Response::deny();
        }

        return Response::allow();
    }

    private function writesSensitiveNotes(): bool
    {
        return request()->exists('medical_note')
            || request()->exists('dietary_note')
            || request()->exists('accessibility_note');
    }
}
