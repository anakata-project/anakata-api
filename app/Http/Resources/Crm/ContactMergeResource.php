<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Models\ContactMerge;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContactMerge
 */
class ContactMergeResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     survivor_id: int,
     *     loser_id: int,
     *     reason: string,
     *     merged_by: int|null,
     *     merged_at: string,
     *     undone_at: string|null,
     *     undone_by: int|null,
     *     undo_reason: string|null,
     *     erased_at: string|null,
     *     repointed_rows: list<array{table: string, id: int}>
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'survivor_id' => $this->survivor_id,
            'loser_id' => $this->loser_id,
            'reason' => $this->reason,
            'merged_by' => $this->merged_by,
            'merged_at' => Iso::utc($this->merged_at),
            'undone_at' => Iso::utc($this->undone_at),
            'undone_by' => $this->undone_by,
            'undo_reason' => $this->undo_reason,
            'erased_at' => Iso::utc($this->erased_at),
            'repointed_rows' => $this->repointed_rows ?? [],
        ];
    }
}
