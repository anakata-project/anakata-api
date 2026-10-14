<?php

declare(strict_types=1);

namespace App\Http\Controllers\Copilot;

use App\Http\Controllers\Controller;
use App\Http\Requests\Copilot\StoreCopilotDecisionsRequest;
use App\Http\Requests\Copilot\StoreCopilotMessageRequest;
use App\Http\Resources\Copilot\CopilotConversationResource;
use App\Http\Resources\Copilot\CopilotMessageResource;
use App\Models\CopilotConversation;
use App\Models\User;
use App\Support\Ai\CopilotDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;

final class CopilotController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $conversations = CopilotConversation::query()
            ->where('participant_type', $user->getMorphClass())
            ->where('participant_id', $user->id)
            ->latest('updated_at')
            ->paginate(20);

        return CopilotConversationResource::collection($conversations);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversation = CopilotConversation::query()->create([
            'id' => (string) Str::uuid7(),
            'participant_type' => $user->getMorphClass(),
            'participant_id' => $user->id,
            'title' => CopilotConversation::UNTITLED,
        ]);

        return (new CopilotConversationResource($conversation))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, CopilotConversation $conversation): AnonymousResourceCollection
    {
        $this->authorize('view', $conversation);

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(50);

        return CopilotMessageResource::collection($messages);
    }

    public function destroy(Request $request, CopilotConversation $conversation): JsonResponse
    {
        $this->authorize('delete', $conversation);
        $conversation->delete();

        return response()->json(['message' => 'Conversation archived.']);
    }

    public function storeMessage(StoreCopilotMessageRequest $request, CopilotConversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        /** @var User $user */
        $user = $request->user();

        return response()->json(
            CopilotDispatcher::dispatchTurn($conversation, $user, $request->validated()),
            202,
        );
    }

    public function storeDecisions(StoreCopilotDecisionsRequest $request, CopilotConversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        /** @var User $user */
        $user = $request->user();

        /** @var array<string, array{action: string, result?: string|null}> $incoming */
        $incoming = $request->validated('decisions');
        $normalized = [];

        foreach ($incoming as $toolCallId => $decision) {
            $normalized[$toolCallId] = $decision['action'] === 'approve'
                ? Decision::approve()
                : Decision::reject($decision['result'] ?? null);
        }

        return response()->json(
            CopilotDispatcher::dispatchDecisions(
                $conversation,
                $user,
                Decisions::from($normalized)->rejectRemaining(),
            ),
            202,
        );
    }
}
