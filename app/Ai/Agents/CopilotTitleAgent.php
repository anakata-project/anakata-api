<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Names a conversation. The cheapest model is enough for a short title.
 */
#[Timeout(20)]
#[MaxTokens(64)]
#[UseCheapestModel]
class CopilotTitleAgent implements Agent
{
    use Promptable;

    public function provider(): string
    {
        return 'anthropic';
    }

    public function instructions(): Stringable|string
    {
        return 'Generate a concise 3-5 word title for a conversation that starts with the following message. Use the same language as the message. Respond with only the title, no quotes or punctuation.';
    }
}
