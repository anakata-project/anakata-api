<?php

declare(strict_types=1);

namespace App\Ai\Middleware;

use App\Ai\Agents\AnakataCopilotAgent;
use App\Enums\Permission;
use App\Enums\UserStatus;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * Queued turns have no HTTP user. Actions and history read Auth::user().
 */
final class ActAsCopilotUser
{
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $agent = $prompt->agent;

        if (! $agent instanceof AnakataCopilotAgent) {
            return $next($prompt);
        }

        $user = $agent->user;

        if ($user->status !== UserStatus::Active || ! $user->hasPermission(Permission::CopilotUse)) {
            throw new AuthorizationException('You do not have permission to use the copilot.');
        }

        Auth::setUser($user);
        Context::add('history_source', 'copilot');

        return $next($prompt);
    }
}
