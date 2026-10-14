<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Crm\RecordContactActivity;
use App\Ai\Tools\Concerns\ReturnsToolJson;
use App\Enums\ActivityKind;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class LogContactActivity implements Approvable, Tool
{
    use InteractsWithApprovals;
    use ReturnsToolJson;

    public function __construct(private readonly User $user) {}

    public function description(): Stringable|string
    {
        return 'Log a call, email, meeting, or note on a contact.';
    }

    public function handle(Request $request): Stringable|string
    {
        $contact = Contact::query()->find($request['contact_id'] ?? null);

        if (! $contact instanceof Contact) {
            return $this->failure('No contact found with that ID.');
        }

        if (! Gate::forUser($this->user)->allows('update', $contact)) {
            return $this->failure('You do not have permission to log activity on this contact.');
        }

        $kind = ActivityKind::tryFrom((string) ($request['kind'] ?? ''));

        if (! $kind instanceof ActivityKind || $kind === ActivityKind::TaskCompleted) {
            return $this->failure('Kind must be CALL, EMAIL, MEETING, or NOTE.');
        }

        Context::add('history_source', 'copilot');

        try {
            $activity = app(RecordContactActivity::class)->handle(
                $contact,
                $kind,
                trim((string) $request['body']),
                isset($request['deal_id']) ? (int) $request['deal_id'] : null,
            );
        } catch (Throwable $exception) {
            return $this->fromException($exception);
        }

        return $this->success([
            'message' => 'Activity logged.',
            'activity_id' => $activity->id,
            'contact_id' => $activity->contact_id,
            'kind' => $activity->kind->value,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'contact_id' => $schema->integer()->description('Contact ID')->required(),
            'kind' => $schema->string()
                ->description('CALL, EMAIL, MEETING, or NOTE')
                ->enum(['CALL', 'EMAIL', 'MEETING', 'NOTE'])
                ->required(),
            'body' => $schema->string()->description('What happened')->required(),
            'deal_id' => $schema->integer()->description('Optional deal on the same contact')->nullable(),
        ];
    }
}
