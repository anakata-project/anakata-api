<?php

declare(strict_types=1);

namespace App\Http\Resources\Privacy;

use App\Models\SubjectRequest;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubjectRequest
 */
#[SchemaName('SubjectRequestResource')]
class SubjectRequestResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     contact_id: int,
     *     type: string,
     *     received_at: string,
     *     due_at: string,
     *     channel: string,
     *     verified_how: string|null,
     *     status: string,
     *     completed_at: string|null,
     *     outcome: string|null,
     *     has_export: bool
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            'type' => $this->type->value,
            'received_at' => $this->received_at->toIso8601String(),
            'due_at' => $this->due_at->toIso8601String(),
            'channel' => $this->channel->value,
            'verified_how' => $this->verified_how,
            'status' => $this->status->value,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'outcome' => $this->outcome,
            'has_export' => $this->export_path !== null,
        ];
    }
}
