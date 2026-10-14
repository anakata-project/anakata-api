<?php

declare(strict_types=1);

use App\Models\CopilotConversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('copilot.{conversationId}', function (User $user, string $conversationId): bool {
    $conversation = CopilotConversation::query()->find($conversationId);

    return $conversation instanceof CopilotConversation
        && $user->can('view', $conversation);
});
