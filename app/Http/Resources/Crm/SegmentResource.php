<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Enums\SegmentDimension;
use App\Enums\SegmentKind;
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
     *     conditions: array{
     *         match: string,
     *         items: list<array{
     *             field: string,
     *             operator: string,
     *             value: int|bool|string|list<string>|list<int>,
     *             event?: string,
     *             within_days?: int|null
     *         }>
     *     },
     *     dimensions: list<array{axis: SegmentDimension, label: string}>,
     *     kind: SegmentKind,
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
            'conditions' => $this->conditions($this->conditions),
            'dimensions' => $this->dimensions($this->dimensions),
            'kind' => $this->segmentKind(),
            'system' => $this->system,
            'active' => $this->active,
            'feeds' => $this->feeds,
            'count' => (int) $this->getAttribute('live_count'),
        ];
    }

    private function segmentKind(): SegmentKind
    {
        return $this->kind;
    }

    /**
     * @param  array{match: string, items: list<array<string, mixed>>}  $conditions
     * @return array{
     *     match: string,
     *     items: list<array{
     *         field: string,
     *         operator: string,
     *         value: int|bool|string|list<string>|list<int>,
     *         event?: string,
     *         within_days?: int|null
     *     }>
     * }
     */
    private function conditions(array $conditions): array
    {
        $items = [];

        foreach ($conditions['items'] as $item) {
            $row = [
                'field' => (string) $item['field'],
                'operator' => (string) $item['operator'],
                'value' => $this->conditionValue($item['value'] ?? null),
            ];

            if (array_key_exists('event', $item) && is_string($item['event'])) {
                $row['event'] = $item['event'];
            }

            if (array_key_exists('within_days', $item)) {
                $row['within_days'] = $item['within_days'] === null ? null : (int) $item['within_days'];
            }

            $items[] = $row;
        }

        return [
            'match' => (string) $conditions['match'],
            'items' => $items,
        ];
    }

    /**
     * @return int|bool|string|list<string>|list<int>
     */
    private function conditionValue(mixed $value): int|bool|string|array
    {
        if (is_int($value) || is_bool($value) || is_string($value)) {
            return $value;
        }

        if (is_array($value) && array_is_list($value)) {
            /** @var list<string>|list<int> $value */
            return $value;
        }

        return '';
    }

    /**
     * @param  list<array{axis: string, label: string}>  $dimensions
     * @return list<array{axis: SegmentDimension, label: string}>
     */
    private function dimensions(array $dimensions): array
    {
        $rows = [];

        foreach ($dimensions as $row) {
            $rows[] = $this->dimension($row);
        }

        return $rows;
    }

    /**
     * @param  array{axis: string, label: string}  $row
     * @return array{axis: SegmentDimension, label: string}
     */
    private function dimension(array $row): array
    {
        return [
            'axis' => $this->dimensionAxis((string) $row['axis']),
            'label' => $row['label'],
        ];
    }

    private function dimensionAxis(string $axis): SegmentDimension
    {
        return SegmentDimension::from($axis);
    }
}
