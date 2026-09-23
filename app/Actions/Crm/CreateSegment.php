<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\SegmentKind;
use App\Models\Segment;
use App\Models\User;
use App\Support\Crm\SegmentVocabulary;
use App\Support\History\History;
use Illuminate\Support\Str;

final class CreateSegment extends Action
{
    /**
     * @param  array{
     *     name: string,
     *     sentence: string,
     *     conditions: array<string, mixed>,
     *     dimensions: list<array{axis: string, label: string}>,
     *     kind: string,
     *     feeds: string,
     *     active?: bool
     * }  $data
     */
    public function handle(array $data, User $actor): Segment
    {
        SegmentVocabulary::assertValid($data['conditions']);

        return $this->transaction(function () use ($data, $actor): Segment {
            $segment = Segment::query()->create([
                'key' => $this->keyFor($data['name']),
                'name' => $data['name'],
                'sentence' => $data['sentence'],
                'conditions' => $data['conditions'],
                'dimensions' => $data['dimensions'],
                'kind' => SegmentKind::from($data['kind']),
                'system' => false,
                'active' => $data['active'] ?? true,
                'feeds' => $data['feeds'],
            ]);

            History::record($segment, 'segment.created', after: [
                'key' => $segment->key,
                'name' => $segment->name,
                'sentence' => $segment->sentence,
                'conditions' => $segment->conditions,
                'dimensions' => $segment->dimensions,
                'kind' => $segment->kind->value,
                'active' => $segment->active,
                'feeds' => $segment->feeds,
            ], actor: $actor);

            return $segment->refresh();
        });
    }

    private function keyFor(string $name): string
    {
        $base = Str::slug($name, '_');
        $base = $base !== '' ? substr($base, 0, 60) : 'segment';
        $key = $base;
        $suffix = 2;

        while (Segment::query()->where('key', $key)->exists()) {
            $key = substr($base, 0, 57).'_'.$suffix;
            $suffix++;
        }

        return $key;
    }
}
