<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItineraryStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use App\Support\Itineraries\Completeness;
use Database\Factories\ItineraryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property ItineraryStatus $status
 * @property int $sort_order
 * @property bool $festive
 * @property int $days
 * @property int $nights
 * @property string $embark
 * @property string $disembark
 * @property string $tagline
 * @property string|null $hero_image_path
 * @property string $hero_alt
 * @property string $fallback_gradient
 * @property string $card_description
 * @property string $overview
 * @property string $long_description
 * @property list<string> $highlights
 * @property list<string> $chips
 * @property list<array{0: string, 1: string}> $facts
 * @property list<array{0: string, 1: string}> $day_plan
 * @property list<string> $included
 * @property list<string> $excluded
 * @property list<array{0: string, 1: string}> $faqs
 * @property string|null $slug
 * @property string $meta_title
 * @property string $meta_description
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'code',
    'name',
    'status',
    'sort_order',
    'festive',
    'days',
    'nights',
    'embark',
    'disembark',
    'tagline',
    'hero_image_path',
    'hero_alt',
    'fallback_gradient',
    'card_description',
    'overview',
    'long_description',
    'highlights',
    'chips',
    'facts',
    'day_plan',
    'included',
    'excluded',
    'faqs',
    'slug',
    'meta_title',
    'meta_description',
])]
class Itinerary extends Model
{
    /** @use HasFactory<ItineraryFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ItineraryStatus::class,
            'sort_order' => 'integer',
            'festive' => 'boolean',
            'days' => 'integer',
            'nights' => 'integer',
            'highlights' => 'array',
            'chips' => 'array',
            'facts' => 'array',
            'day_plan' => 'array',
            'included' => 'array',
            'excluded' => 'array',
            'faqs' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Itinerary $itinerary): void {
            if ($itinerary->isDirty('code')) {
                throw new LogicException('An itinerary code cannot be changed after creation.');
            }
        });
    }

    /**
     * @return MorphMany<ChangeHistory, $this>
     */
    public function history(): MorphMany
    {
        return $this->morphMany(ChangeHistory::class, 'subject');
    }

    public function completeness(): Completeness
    {
        return Completeness::for($this);
    }

    public function departuresCount(): int
    {
        return 0;
    }
}
