<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\Permission;
use App\Enums\UserStatus;
use App\Models\Deal;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AssignDeal extends Action
{
    public function handle(Deal $deal, User $actor, ?int $assigneeId): Deal
    {
        $actOnAny = $actor->hasPermission(Permission::RecordsActOnAny);

        if ($deal->owner_id === null) {
            $assigneeId = $actOnAny && $assigneeId !== null ? $assigneeId : $actor->id;
        } elseif (! $actOnAny) {
            throw new HttpException(422, 'This deal already has an owner.');
        } else {
            $assigneeId ??= $actor->id;
        }

        $assignee = User::query()
            ->whereKey($assigneeId)
            ->where('status', UserStatus::Active->value)
            ->first();

        if (! $assignee instanceof User) {
            throw ValidationException::withMessages([
                'user_id' => ['Choose an active user.'],
            ]);
        }

        return $this->transaction(function () use ($deal, $assignee): Deal {
            $before = $deal->owner_id;
            $deal->forceFill(['owner_id' => $assignee->id])->save();

            History::record($deal, 'deal.assigned', before: [
                'owner_id' => $before,
            ], after: [
                'owner_id' => $assignee->id,
            ]);

            return $deal->refresh();
        });
    }
}
