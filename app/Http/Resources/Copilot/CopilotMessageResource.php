<?php

declare(strict_types=1);

namespace App\Http\Resources\Copilot;

use App\Support\Iso;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Ai\Models\ConversationMessage;

/**
 * @mixin ConversationMessage
 */
#[SchemaName('CopilotMessage')]
class CopilotMessageResource extends JsonResource
{
    /**
     * @return array{id: string, role: string, content: string, tool_calls: mixed, tool_results: mixed, created_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => (string) $this->content,
            'tool_calls' => $this->tool_calls,
            'tool_results' => $this->tool_results,
            'created_at' => Iso::utc($this->created_at),
        ];
    }
}
