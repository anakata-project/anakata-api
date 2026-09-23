<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SegmentKind;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $sentence
 * @property array{match: string, items: list<array<string, mixed>>} $conditions
 * @property list<array{axis: string, label: string}> $dimensions
 * @property SegmentKind $kind
 * @property bool $system
 * @property bool $active
 * @property string $feeds
 * @property int|null $created_by
 * @property int|null $updated_by
 */
#[Fillable([
    'key',
    'name',
    'sentence',
    'conditions',
    'dimensions',
    'kind',
    'system',
    'active',
    'feeds',
])]
class Segment extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * Prototype order. Custom definitions follow, by key.
     *
     * @var list<string>
     */
    public const SYSTEM_KEYS = [
        'warm_dreamers',
        'abandoned_checkout',
        'holding_not_paid',
        'festive_prospects',
        'families_6_17',
        'past_guests_high_ltv',
        'advisors_non_producing',
        'dach_luxury',
        'suppressed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'dimensions' => 'array',
            'kind' => SegmentKind::class,
            'system' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * @param  Builder<Segment>  $query
     */
    public function scopeInDisplayOrder(Builder $query): void
    {
        $list = implode(', ', array_map(
            fn (string $key): string => "'".$key."'",
            self::SYSTEM_KEYS,
        ));

        $query->orderByRaw('CASE WHEN `key` IN ('.$list.') THEN FIELD(`key`, '.$list.') ELSE 1000 END')
            ->orderBy('key');
    }

    /**
     * @return MorphMany<ChangeHistory, $this>
     */
    public function history(): MorphMany
    {
        return $this->morphMany(ChangeHistory::class, 'subject');
    }

    public function historyLabel(): string
    {
        return $this->name;
    }
}
