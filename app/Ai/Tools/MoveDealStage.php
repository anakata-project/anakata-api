<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Crm\MoveDealStage as MoveDealStageAction;
use App\Ai\Tools\Concerns\ReturnsToolJson;
use App\Enums\DealStage;
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

class MoveDealStage implements Approvable, Tool
{
    use InteractsWithApprovals;
    use ReturnsToolJson;

    public function __construct(private readonly User $user) {}

    public function description(): Stringable|string
    {
        return 'Move an unbound deal between CRM stages. A bound deal follows its booking and cannot be moved here. '
            .'Lost and reopening require a reason. You can only move a deal you own unless you can act on any record.';
    }

    public function handle(Request $request): Stringable|string
    {
        $deal = Deal::query()->find($request['deal_id'] ?? null);

        if (! $deal instanceof Deal) {
            return $this->failure('No deal found with that ID.');
        }

        if (! Gate::forUser($this->user)->allows('update', $deal)) {
            return $this->failure('You do not have permission to move this deal.');
        }

        $stage = DealStage::tryFrom((string) ($request['stage'] ?? ''));

        if (! $stage instanceof DealStage) {
            return $this->failure('That stage is not recognised.');
        }

        Context::add('history_source', 'copilot');

        try {
            $deal = app(MoveDealStageAction::class)->handle(
                $deal,
                $this->user,
                $stage,
                isset($request['reason']) ? (string) $request['reason'] : null,
            );
        } catch (Throwable $exception) {
            return $this->fromException($exception);
        }

        return $this->success([
            'message' => "Deal '{$deal->title}' is now {$deal->stage?->label()}.",
            'deal_id' => $deal->id,
            'stage' => $deal->stage?->value,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'deal_id' => $schema->integer()->description('Deal ID')->required(),
            'stage' => $schema->string()
                ->description('NEW_LEAD, QUALIFYING, QUOTED, NEGOTIATION, or LOST')
                ->enum(array_map(fn (DealStage $stage): string => $stage->value, DealStage::stored()))
                ->required(),
            'reason' => $schema->string()->description('Required when marking lost or reopening a lost deal')->nullable(),
        ];
    }
}
