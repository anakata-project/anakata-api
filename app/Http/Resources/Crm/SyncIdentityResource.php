<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Models\ContactMerge;
use App\Models\User;
use App\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContactMerge
 */
class SyncIdentityResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     survivor_id: int,
     *     loser_id: int,
     *     reason: string,
     *     merged_by: string,
     *     merged_at: string,
     *     undone: bool,
     *     undone_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'survivor_id' => $this->survivor_id,
            'loser_id' => $this->loser_id,
            'reason' => $this->reason,
            'merged_by' => $this->mergedBy instanceof User ? $this->mergedBy->name : 'System',
            'merged_at' => Iso::utc($this->merged_at),
            'undone' => $this->undone_at !== null,
            'undone_at' => Iso::utc($this->undone_at),
        ];
    }
}
