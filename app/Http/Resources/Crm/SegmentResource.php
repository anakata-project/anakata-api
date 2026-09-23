<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Models\Segment;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Segment
 */
#[SchemaName('CrmSegmentResource')]
class SegmentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     key: string,
     *     name: string,
     *     sentence: string,
     *     conditions: array{match: string, items: list<array<string, mixed>>},
     *     dimensions: list<array{axis: string, label: string}>,
     *     kind: string,
     *     system: bool,
     *     active: bool,
     *     feeds: string,
     *     count: int
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'sentence' => $this->sentence,
            'conditions' => $this->conditions,
            'dimensions' => $this->dimensions,
            'kind' => $this->kind->value,
            'system' => $this->system,
            'active' => $this->active,
            'feeds' => $this->feeds,
            'count' => (int) $this->getAttribute('live_count'),
        ];
    }
}
