<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Ai\Models\Conversation;

/**
 * SDK conversation row with a soft archive. The SDK writes the columns.
 * Ownership is the participant, so this row has no audit columns.
 */
class CopilotConversation extends Conversation
{
    use SoftDeletes;

    public const UNTITLED = 'New conversation';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }
}
