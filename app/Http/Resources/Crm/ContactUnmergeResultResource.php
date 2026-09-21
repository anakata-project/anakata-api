<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Models\ContactMerge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ContactMerge $merge
 * @property list<array{table: string, id: int}> $skipped_rows
 */
class ContactUnmergeResultResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{skipped_rows: list<array{table: string, id: int}>, merge: ContactMergeResource}
     */
    public function toArray(Request $request): array
    {
        /** @var array{merge: ContactMerge, skipped_rows: list<array{table: string, id: int}>} $result */
        $result = $this->resource;

        return [
            'skipped_rows' => $result['skipped_rows'],
            'merge' => new ContactMergeResource($result['merge']),
        ];
    }
}
