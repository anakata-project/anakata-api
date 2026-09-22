<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\Permission;
use App\Enums\TaskKind;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Enums\UserStatus;
use App\Models\Contact;
use App\Models\CrmTask;
use App\Models\Deal;
use App\Models\User;
use App\Support\History\History;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class SaveManualTask extends Action
{
    public function create(
        User $actor,
        string $title,
        CarbonInterface $dueAt,
        Contact $contact,
        ?int $dealId,
        ?int $ownerId,
    ): CrmTask {
        $ownerId = $this->owner($actor, $ownerId);

        return $this->transaction(function () use ($title, $dueAt, $contact, $dealId, $ownerId): CrmTask {
            $resolved = Contact::resolveIdentity($contact->id) ?? $contact;
            $deal = $this->dealFor($resolved, $dealId);

            $task = CrmTask::query()->create([
                'title' => $title,
                'context' => 'Manual',
                'contact_id' => $resolved->id,
                'deal_id' => $deal?->id,
                'owner_id' => $ownerId,
                'due_at' => $dueAt,
                'source' => TaskSource::User,
                'kind' => TaskKind::Manual,
                'status' => TaskStatus::Open,
            ]);

            History::record($task, 'task.raised', after: [
                'kind' => TaskKind::Manual->value,
                'source' => TaskSource::User->value,
                'title' => $title,
            ]);

            return $task->refresh();
        });
    }

    public function update(CrmTask $task, User $actor, ?string $title, ?CarbonInterface $dueAt, ?int $ownerId): CrmTask
    {
        if ($task->source !== TaskSource::User) {
            throw new HttpException(422, 'A system task follows the RMS.');
        }

        if ($task->status !== TaskStatus::Open) {
            throw new HttpException(422, 'This task is already closed.');
        }

        if ($task->owner_id !== $actor->id && ! $actor->hasPermission(Permission::RecordsActOnAny)) {
            throw new HttpException(403, 'You cannot change this task.');
        }

        return $this->transaction(function () use ($task, $actor, $title, $dueAt, $ownerId): CrmTask {
            $task->forceFill([
                'title' => $title ?? $task->title,
                'due_at' => $dueAt ?? $task->due_at,
                'owner_id' => $ownerId === null ? $task->owner_id : $this->owner($actor, $ownerId),
            ])->save();

            return $task->refresh();
        });
    }

    private function owner(User $actor, ?int $ownerId): int
    {
        $ownerId ??= $actor->id;

        if ($ownerId !== $actor->id && ! $actor->hasPermission(Permission::RecordsActOnAny)) {
            throw new HttpException(403, 'You cannot assign this task to someone else.');
        }

        $assignee = User::query()->whereKey($ownerId)->where('status', UserStatus::Active->value)->first();

        if (! $assignee instanceof User) {
            throw ValidationException::withMessages([
                'owner_id' => ['Choose an active user.'],
            ]);
        }

        return $assignee->id;
    }

    private function dealFor(Contact $contact, ?int $dealId): ?Deal
    {
        if ($dealId === null) {
            return null;
        }

        $deal = Deal::query()->find($dealId);

        if (! $deal instanceof Deal || $deal->contact_id !== $contact->id) {
            throw ValidationException::withMessages([
                'deal_id' => ['That deal belongs to another contact.'],
            ]);
        }

        return $deal;
    }
}
