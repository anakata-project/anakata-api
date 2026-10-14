<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CopilotConversation;
use App\Models\User;

final class CopilotConversationPolicy extends Policy
{
    public function view(User $user, CopilotConversation $conversation): bool
    {
        return $this->owns($user, $conversation);
    }

    public function delete(User $user, CopilotConversation $conversation): bool
    {
        return $this->owns($user, $conversation);
    }

    private function owns(User $user, CopilotConversation $conversation): bool
    {
        return $conversation->participant_type === $user->getMorphClass()
            && (int) $conversation->participant_id === (int) $user->id;
    }
}
