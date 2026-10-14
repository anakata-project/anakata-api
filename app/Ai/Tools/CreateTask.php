<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Crm\SaveManualTask;
use App\Ai\Tools\Concerns\ReturnsToolJson;
use App\Models\Contact;
use App\Models\CrmTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class CreateTask implements Approvable, Tool
{
    use InteractsWithApprovals;
    use ReturnsToolJson;

    public function __construct(private readonly User $user) {}

    public function description(): Stringable|string
    {
        return 'Raise a manual CRM task on a contact. The task is owned by the signed-in user.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! Gate::forUser($this->user)->allows('create', CrmTask::class)) {
            return $this->failure('You do not have permission to create tasks.');
        }

        $contact = Contact::query()->find($request['contact_id'] ?? null);

        if (! $contact instanceof Contact) {
            return $this->failure('No contact found with that ID.');
        }

        Context::add('history_source', 'copilot');

        try {
            $task = app(SaveManualTask::class)->create(
                $this->user,
                trim((string) $request['title']),
                CarbonImmutable::parse((string) $request['due_at']),
                $contact,
                isset($request['deal_id']) ? (int) $request['deal_id'] : null,
                null,
            );
        } catch (Throwable $exception) {
            return $this->fromException($exception);
        }

        return $this->success([
            'message' => 'Task created.',
            'task_id' => $task->id,
            'title' => $task->title,
            'contact_id' => $task->contact_id,
            'due_at' => $task->due_at->format('Y-m-d'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'contact_id' => $schema->integer()->description('Contact the task belongs to')->required(),
            'title' => $schema->string()->description('Task title')->required(),
            'due_at' => $schema->string()->description('Due date YYYY-MM-DD. Resolve relative phrases with ResolveCalendar first.')->required(),
            'deal_id' => $schema->integer()->description('Optional deal on the same contact')->nullable(),
        ];
    }
}
