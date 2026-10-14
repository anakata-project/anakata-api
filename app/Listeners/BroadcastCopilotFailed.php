<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Ai\Agents\AnakataCopilotAgent;
use App\Events\CopilotFailed;
use Illuminate\Queue\Events\JobFailed;
use Laravel\Ai\Jobs\BroadcastAgent;
use Throwable;

final class BroadcastCopilotFailed
{
    public function handle(JobFailed $event): void
    {
        try {
            $command = unserialize($event->job->payload()['data']['command'] ?? '');
        } catch (Throwable) {
            return;
        }

        if (! $command instanceof BroadcastAgent || ! $command->agent instanceof AnakataCopilotAgent) {
            return;
        }

        $conversationId = $command->agent->currentConversation();

        if ($conversationId === null || $conversationId === '') {
            return;
        }

        event(new CopilotFailed(
            conversationId: $conversationId,
            callId: $command->invocationId,
        ));
    }
}
