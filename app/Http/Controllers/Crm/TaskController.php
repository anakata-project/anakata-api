<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Actions\Crm\CloseTask;
use App\Actions\Crm\RecordContactActivity;
use App\Actions\Crm\SaveManualTask;
use App\Enums\ActivityKind;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\CompleteTaskRequest;
use App\Http\Requests\Crm\StoreContactActivityRequest;
use App\Http\Requests\Crm\StoreManualTaskRequest;
use App\Http\Requests\Crm\UpdateManualTaskRequest;
use App\Http\Resources\Crm\ContactActivityResource;
use App\Http\Resources\Crm\TaskListResource;
use App\Models\Contact;
use App\Models\CrmTask;
use App\Models\User;
use App\Support\Crm\TaskList;
use App\Support\Iso;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class TaskController extends Controller
{
    public function index(Request $request): TaskListResource
    {
        $this->authorize('viewAny', CrmTask::class);
        /** @var User $actor */
        $actor = $request->user();
        $scope = $request->query('scope');
        $scope = is_string($scope) ? $scope : 'mine';

        if ($scope === 'all' && ! $actor->hasPermission(Permission::RecordsActOnAny)) {
            throw new HttpException(403, 'You cannot see every task.');
        }

        return new TaskListResource(TaskList::build($actor, [
            'scope' => $scope,
            'status' => is_string($request->query('status')) ? $request->query('status') : 'open',
            'kind' => is_string($request->query('kind')) ? $request->query('kind') : null,
            'due' => is_string($request->query('due')) ? $request->query('due') : null,
        ]));
    }

    public function store(StoreManualTaskRequest $request, SaveManualTask $action): JsonResponse
    {
        $this->authorize('create', CrmTask::class);
        /** @var User $actor */
        $actor = $request->user();
        $contact = Contact::query()->findOrFail($request->integer('contact_id'));

        $task = $action->create(
            $actor,
            $request->string('title')->toString(),
            CarbonImmutable::parse($request->string('due_at')->toString()),
            $contact,
            $request->filled('deal_id') ? $request->integer('deal_id') : null,
            $request->filled('owner_id') ? $request->integer('owner_id') : null,
        );

        return response()->json([
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status->value,
        ]);
    }

    public function update(UpdateManualTaskRequest $request, CrmTask $task, SaveManualTask $action): JsonResponse
    {
        $this->authorize('view', $task);
        /** @var User $actor */
        $actor = $request->user();

        $task = $action->update(
            $task,
            $actor,
            $request->filled('title') ? $request->string('title')->toString() : null,
            $request->filled('due_at') ? CarbonImmutable::parse($request->string('due_at')->toString()) : null,
            $request->exists('owner_id') ? ($request->filled('owner_id') ? $request->integer('owner_id') : null) : null,
        );

        return response()->json(['id' => $task->id, 'title' => $task->title]);
    }

    public function complete(CompleteTaskRequest $request, CrmTask $task, CloseTask $action): JsonResponse
    {
        $this->authorize('view', $task);
        /** @var User $actor */
        $actor = $request->user();
        $task = $action->complete($task, $actor, CloseTask::requireOutcome($request->string('outcome')->toString()));

        return response()->json(['id' => $task->id, 'status' => $task->status->value]);
    }

    public function cancel(CompleteTaskRequest $request, CrmTask $task, CloseTask $action): JsonResponse
    {
        $this->authorize('view', $task);
        /** @var User $actor */
        $actor = $request->user();
        $task = $action->cancel($task, $actor, CloseTask::requireOutcome($request->string('outcome')->toString()));

        return response()->json(['id' => $task->id, 'status' => $task->status->value]);
    }

    public function storeActivity(StoreContactActivityRequest $request, Contact $contact, RecordContactActivity $action): ContactActivityResource
    {
        $this->authorize('update', $contact);

        $activity = $action->handle(
            $contact,
            ActivityKind::from($request->string('kind')->toString()),
            $request->string('body')->toString(),
            $request->filled('deal_id') ? $request->integer('deal_id') : null,
        );

        return new ContactActivityResource([
            'id' => $activity->id,
            'kind' => $activity->kind->value,
            'body' => $activity->body,
            'occurred_at' => Iso::utc($activity->occurred_at),
        ]);
    }
}
