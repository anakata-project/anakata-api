<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\ActivityKind;
use App\Enums\Permission;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\ContactActivity;
use App\Models\CrmTask;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CloseTask extends Action
{
    public function autoClose(CrmTask $task, string $fact): CrmTask
    {
        if ($task->status !== TaskStatus::Open) {
            return $task;
        }

        return $this->transaction(function () use ($task, $fact): CrmTask {
            $task->forceFill([
                'status' => TaskStatus::AutoClosed,
                'closed_at' => Carbon::now(),
                'outcome' => 'Resolved in the RMS',
            ])->save();

            History::record($task, 'task.auto_closed', after: [
                'fact' => $fact,
                'outcome' => 'Resolved in the RMS',
            ], system: true);

            return $task->refresh();
        });
    }

    public function complete(CrmTask $task, User $actor, string $outcome): CrmTask
    {
        $this->assertMayAct($task, $actor);

        if ($task->status !== TaskStatus::Open) {
            throw new HttpException(422, 'This task is already closed.');
        }

        return $this->transaction(function () use ($task, $actor, $outcome): CrmTask {
            $task->forceFill([
                'status' => TaskStatus::Done,
                'closed_at' => Carbon::now(),
                'closed_by' => $actor->id,
                'outcome' => $outcome,
            ])->save();

            if ($task->contact_id !== null) {
                ContactActivity::query()->create([
                    'contact_id' => $task->contact_id,
                    'kind' => ActivityKind::TaskCompleted,
                    'body' => $outcome,
                    'occurred_at' => Carbon::now(),
                    'deal_id' => $task->deal_id,
                    'crm_task_id' => $task->id,
                ]);
            }

            History::record($task, 'task.completed', after: [
                'outcome' => $outcome,
            ]);

            return $task->refresh();
        });
    }

    public function cancel(CrmTask $task, User $actor, string $outcome): CrmTask
    {
        $this->assertMayAct($task, $actor);

        if ($task->source !== TaskSource::User) {
            throw new HttpException(422, 'A system task closes itself when the RMS state clears.');
        }

        if ($task->status !== TaskStatus::Open) {
            throw new HttpException(422, 'This task is already closed.');
        }

        return $this->transaction(function () use ($task, $actor, $outcome): CrmTask {
            $task->forceFill([
                'status' => TaskStatus::Cancelled,
                'closed_at' => Carbon::now(),
                'closed_by' => $actor->id,
                'outcome' => $outcome,
            ])->save();

            History::record($task, 'task.cancelled', after: [
                'outcome' => $outcome,
            ]);

            return $task->refresh();
        });
    }

    private function assertMayAct(CrmTask $task, User $actor): void
    {
        if ($task->owner_id === $actor->id || $actor->hasPermission(Permission::RecordsActOnAny)) {
            return;
        }

        if ($task->needs_permission instanceof Permission && $actor->hasPermission($task->needs_permission)) {
            return;
        }

        throw new HttpException(403, 'You cannot close this task.');
    }

    public static function requireOutcome(?string $outcome): string
    {
        $outcome = trim((string) $outcome);

        if ($outcome === '') {
            throw ValidationException::withMessages([
                'outcome' => ['An outcome is required.'],
            ]);
        }

        return $outcome;
    }
}
