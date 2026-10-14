<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Crm\CreateUnboundDeal;
use App\Ai\Tools\Concerns\ReturnsToolJson;
use App\Enums\DealStage;
use App\Enums\DealType;
use App\Models\Contact;
use App\Models\Deal;
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

class CreateDeal implements Approvable, Tool
{
    use InteractsWithApprovals;
    use ReturnsToolJson;

    public function __construct(private readonly User $user) {}

    public function description(): Stringable|string
    {
        return 'Create an unbound CRM deal on a contact. Stages 5 to 7 follow a booking and cannot be set here.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! Gate::forUser($this->user)->allows('create', Deal::class)) {
            return $this->failure('You do not have permission to create deals.');
        }

        $contact = Contact::query()->find($request['contact_id'] ?? null);

        if (! $contact instanceof Contact) {
            return $this->failure('No contact found with that ID.');
        }

        $type = DealType::tryFrom((string) ($request['type'] ?? ''));
        $stage = DealStage::tryFrom((string) ($request['stage'] ?? DealStage::NewLead->value));

        if (! $type instanceof DealType || ! $stage instanceof DealStage) {
            return $this->failure('Type or stage is not recognised.');
        }

        Context::add('history_source', 'copilot');

        try {
            $deal = app(CreateUnboundDeal::class)->handle(
                $contact,
                $this->user,
                trim((string) $request['title']),
                $type,
                $stage,
                isset($request['estimate']) ? (int) $request['estimate'] : null,
                isset($request['notes']) ? (string) $request['notes'] : null,
            );
        } catch (Throwable $exception) {
            return $this->fromException($exception);
        }

        return $this->success([
            'message' => "Deal '{$deal->title}' created.",
            'deal_id' => $deal->id,
            'title' => $deal->title,
            'stage' => $deal->stage?->value,
            'contact_id' => $deal->contact_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'contact_id' => $schema->integer()->description('Contact ID')->required(),
            'title' => $schema->string()->description('Deal title')->required(),
            'type' => $schema->string()
                ->description('FIT, GROUP, CHARTER, or AGENCY')
                ->enum(array_map(fn (DealType $type): string => $type->value, DealType::cases()))
                ->required(),
            'stage' => $schema->string()
                ->description('NEW_LEAD, QUALIFYING, QUOTED, or NEGOTIATION')
                ->enum(array_map(fn (DealStage $stage): string => $stage->value, DealStage::open()))
                ->nullable(),
            'estimate' => $schema->integer()->description('Estimate in whole US dollars')->nullable(),
            'notes' => $schema->string()->description('Internal notes')->nullable(),
        ];
    }
}
