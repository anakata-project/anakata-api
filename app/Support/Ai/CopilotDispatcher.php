<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Ai\Agents\AnakataCopilotAgent;
use App\Jobs\GenerateCopilotConversationTitle;
use App\Models\CopilotConversation;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Jobs\BroadcastAgent;

/**
 * Dispatches copilot turns onto the ai queue and returns the 202 payload.
 */
final class CopilotDispatcher
{
    private const IDEMPOTENCY_TTL_SECONDS = 600;

    /**
     * @param  array{message: string, client_message_id: string}  $validated
     * @return array{call_id: string, conversation_id: string, channel: string}
     */
    public static function dispatchTurn(CopilotConversation $conversation, User $user, array $validated): array
    {
        $cacheKey = self::idempotencyKey($conversation->id, $validated['client_message_id']);
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && isset($cached['call_id'], $cached['conversation_id'], $cached['channel'])) {
            return [
                'call_id' => (string) $cached['call_id'],
                'conversation_id' => (string) $cached['conversation_id'],
                'channel' => (string) $cached['channel'],
            ];
        }

        if ($conversation->title === CopilotConversation::UNTITLED) {
            GenerateCopilotConversationTitle::dispatch(
                $conversation->id,
                $validated['message'],
            );
        }

        $payload = self::dispatchPrompt($conversation, $user, $validated['message']);
        Cache::put($cacheKey, $payload, self::IDEMPOTENCY_TTL_SECONDS);

        return $payload;
    }

    /**
     * @return array{call_id: string, conversation_id: string, channel: string}
     */
    public static function dispatchDecisions(
        CopilotConversation $conversation,
        User $user,
        Decisions $decisions,
    ): array {
        return self::dispatchPrompt($conversation, $user, $decisions);
    }

    /**
     * @return array{call_id: string, conversation_id: string, channel: string}
     */
    private static function dispatchPrompt(
        CopilotConversation $conversation,
        User $user,
        Decisions|string $prompt,
    ): array {
        $channelName = "copilot.{$conversation->id}";
        $channel = new PrivateChannel($channelName);

        $queued = (new AnakataCopilotAgent($user))
            ->continue($conversation->id, as: $user)
            ->broadcastOnQueue($prompt, $channel);

        $queued->onQueue('ai');

        $callId = (string) Str::uuid7();
        $job = $queued->getJob();

        if ($job instanceof BroadcastAgent) {
            $callId = $job->invocationId;
        }

        return [
            'call_id' => $callId,
            'conversation_id' => $conversation->id,
            'channel' => "private-{$channelName}",
        ];
    }

    private static function idempotencyKey(string $conversationId, string $clientMessageId): string
    {
        return "copilot:dispatch:{$conversationId}:{$clientMessageId}";
    }
}
