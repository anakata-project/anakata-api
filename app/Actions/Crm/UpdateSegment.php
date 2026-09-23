<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\SegmentKind;
use App\Exceptions\ConflictException;
use App\Models\Segment;
use App\Models\User;
use App\Support\Crm\SegmentVocabulary;
use App\Support\History\History;

final class UpdateSegment extends Action
{
    /**
     * @param  array{
     *     name?: string,
     *     sentence?: string,
     *     conditions?: array<string, mixed>,
     *     dimensions?: list<array{axis: string, label: string}>,
     *     kind?: string,
     *     feeds?: string,
     *     active?: bool
     * }  $data
     */
    public function handle(Segment $segment, array $data, User $actor): Segment
    {
        if ($segment->system) {
            throw new ConflictException('A system segment cannot be edited.');
        }

        if (array_key_exists('conditions', $data)) {
            SegmentVocabulary::assertValid($data['conditions']);
        }

        return $this->transaction(function () use ($segment, $data, $actor): Segment {
            $before = $this->snapshot($segment);

            if (array_key_exists('name', $data)) {
                $segment->name = $data['name'];
            }

            if (array_key_exists('sentence', $data)) {
                $segment->sentence = $data['sentence'];
            }

            if (array_key_exists('conditions', $data)) {
                $segment->conditions = $data['conditions'];
            }

            if (array_key_exists('dimensions', $data)) {
                $segment->dimensions = $data['dimensions'];
            }

            if (array_key_exists('kind', $data)) {
                $segment->kind = SegmentKind::from($data['kind']);
            }

            if (array_key_exists('feeds', $data)) {
                $segment->feeds = $data['feeds'];
            }

            if (array_key_exists('active', $data)) {
                $segment->active = $data['active'];
            }

            $segment->save();
            $segment->refresh();
            $after = $this->snapshot($segment);

            if ($before !== $after) {
                History::record($segment, 'segment.updated', before: $before, after: $after, actor: $actor);
            }

            return $segment;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Segment $segment): array
    {
        return [
            'name' => $segment->name,
            'sentence' => $segment->sentence,
            'conditions' => $segment->conditions,
            'dimensions' => $segment->dimensions,
            'kind' => $segment->kind->value,
            'active' => $segment->active,
            'feeds' => $segment->feeds,
        ];
    }
}
