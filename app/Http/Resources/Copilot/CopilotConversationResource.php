<?php

declare(strict_types=1);

namespace App\Http\Resources\Copilot;

use App\Models\CopilotConversation;
use App\Support\Iso;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CopilotConversation
 */
#[SchemaName('CopilotConversation')]
class CopilotConversationResource extends JsonResource
{
    /**
     * @return array{id: string, title: string, created_at: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'created_at' => Iso::utc($this->created_at),
            'updated_at' => Iso::utc($this->updated_at),
        ];
    }
}
