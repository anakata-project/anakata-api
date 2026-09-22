<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\Permission;
use App\Enums\TaskStatus;
use App\Models\CrmTask;
use App\Models\User;
use App\Services\Config\CurrentConfig;
use App\Support\BusinessTime;
use App\Support\Iso;
use Illuminate\Database\Eloquent\Builder;

final class TaskList
{
    /**
     * @param  array{scope?: string|null, status?: string|null, kind?: string|null, due?: string|null}  $filters
     * @return array{
     *     data: list<array{
     *         id: int,
     *         title: string,
     *         context: string|null,
     *         kind: string,
     *         source: string,
     *         source_label: string,
     *         status: string,
     *         due_at: string,
     *         priority: string,
     *         owner: array{id: int, name: string}|null,
     *         needs_permission: string|null,
     *         contact: array{id: int, name: string}|null,
     *         deal: array{id: int, title: string}|null,
     *         booking: array{id: int|null, reference: string}|null,
     *         may_complete: bool
     *     }>,
     *     meta: array{kpis: array{open: int, breached: int, near: int, system: int, quote_sla_hours: int}}
     * }
     */
    public static function build(User $actor, array $filters): array
    {
        $scope = $filters['scope'] ?? 'mine';
        $hours = app(CurrentConfig::class)->businessRules()->sla->responseHours;
        $query = self::visible($actor, $scope);

        $kpis = self::kpis(clone $query, $hours);

        $status = $filters['status'] ?? 'open';

        if ($status === 'closed') {
            $query->where('status', '!=', TaskStatus::Open->value);
        } else {
            $query->where('status', TaskStatus::Open->value);
        }

        $kind = $filters['kind'] ?? null;

        if (is_string($kind) && $kind !== '') {
            $query->where('kind', $kind);
        }

        self::due($query, $filters['due'] ?? null);

        $tasks = $query->with(['owner', 'contact', 'deal', 'booking'])->orderBy('due_at')->orderBy('id')->get();

        return [
            'data' => $tasks->map(fn (CrmTask $task): array => self::row($task, $actor, $hours))->all(),
            'meta' => ['kpis' => $kpis],
        ];
    }

    /**
     * @return Builder<CrmTask>
     */
    private static function visible(User $actor, string $scope): Builder
    {
        $query = CrmTask::query();

        if ($scope === 'all') {
            return $query;
        }

        if ($scope === 'unassigned') {
            return $query->whereNull('owner_id');
        }

        $permissions = $actor->permissions()->map(fn (Permission $permission): string => $permission->value)->all();

        return $query->where(function (Builder $inner) use ($actor, $permissions): void {
            $inner->where('owner_id', $actor->id);

            if ($permissions !== []) {
                $inner->orWhereIn('needs_permission', $permissions);
            }
        });
    }

    /**
     * @param  Builder<CrmTask>  $query
     */
    private static function due(Builder $query, mixed $due): void
    {
        if (! is_string($due) || $due === '') {
            return;
        }

        $now = now();
        $start = BusinessTime::now()->startOfDay()->utc();
        $end = BusinessTime::now()->endOfDay()->utc();

        if ($due === 'overdue') {
            $query->where('due_at', '<', $now);
        } elseif ($due === 'today') {
            $query->whereBetween('due_at', [$start, $end]);
        } elseif ($due === 'week') {
            $query->whereBetween('due_at', [$start, $end->addDays(6)]);
        }
    }

    /**
     * @param  Builder<CrmTask>  $query
     * @return array{open: int, breached: int, near: int, system: int, quote_sla_hours: int}
     */
    private static function kpis(Builder $query, int $hours): array
    {
        $end = BusinessTime::now()->endOfDay()->utc();
        $now = now();
        $open = (clone $query)->where('status', TaskStatus::Open->value);

        return [
            'open' => (clone $open)->count(),
            'breached' => (clone $open)->where('due_at', '<', $now)->count(),
            'near' => (clone $open)->where('due_at', '>=', $now)->where('due_at', '<=', $end)->count(),
            'system' => (clone $open)->where('source', 'SYSTEM')->count(),
            'quote_sla_hours' => $hours,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     context: string|null,
     *     kind: string,
     *     source: string,
     *     source_label: string,
     *     status: string,
     *     due_at: string,
     *     priority: string,
     *     owner: array{id: int, name: string}|null,
     *     needs_permission: string|null,
     *     contact: array{id: int, name: string}|null,
     *     deal: array{id: int, title: string}|null,
     *     booking: array{id: int|null, reference: string}|null,
     *     may_complete: bool
     * }
     */
    private static function row(CrmTask $task, User $actor, int $hours): array
    {
        $priority = 'ok';

        if ($task->due_at->lt(now())) {
            $priority = 'bad';
        } elseif ($task->due_at->lte(BusinessTime::now()->endOfDay()->utc())) {
            $priority = 'warn';
        }

        $reference = $task->booking !== null ? TaskSweep::reference($task->booking) : null;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'context' => $task->context,
            'kind' => $task->kind->value,
            'source' => $task->source->value,
            'source_label' => $task->kind->sourceLabel($hours),
            'status' => $task->status->value,
            'due_at' => Iso::utc($task->due_at),
            'priority' => $priority,
            'owner' => $task->owner === null ? null : [
                'id' => $task->owner->id,
                'name' => $task->owner->name,
            ],
            'needs_permission' => $task->needs_permission?->value,
            'contact' => $task->contact === null ? null : [
                'id' => $task->contact->id,
                'name' => $task->contact->name,
            ],
            'deal' => $task->deal === null ? null : [
                'id' => $task->deal->id,
                'title' => $task->deal->title,
            ],
            'booking' => $reference === null ? null : [
                'id' => $task->booking_id,
                'reference' => $reference,
            ],
            'may_complete' => self::mayComplete($task, $actor),
        ];
    }

    private static function mayComplete(CrmTask $task, User $actor): bool
    {
        if ($task->status !== TaskStatus::Open) {
            return false;
        }

        if ($task->owner_id === $actor->id || $actor->hasPermission(Permission::RecordsActOnAny)) {
            return true;
        }

        return $task->needs_permission instanceof Permission && $actor->hasPermission($task->needs_permission);
    }
}
