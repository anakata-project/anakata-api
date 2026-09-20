<?php

declare(strict_types=1);

namespace App\Support\Itineraries;

use App\Models\Itinerary;

/**
 * Prototype `itinChecks`, same order and labels.
 */
final readonly class Completeness
{
    /**
     * @param  list<string>  $missing
     * @param  list<string>  $blocking
     */
    public function __construct(
        public int $pct,
        public array $missing,
        public array $blocking,
    ) {}

    public static function for(Itinerary $itinerary): self
    {
        $checks = [
            ['name', $itinerary->name !== '', true],
            ['card description', $itinerary->card_description !== '', true],
            ['day-by-day plan', $itinerary->day_plan !== [], true],
            ['days / nights', $itinerary->days > 0 && $itinerary->nights > 0, true],
            ['hero photo', is_string($itinerary->hero_image_path) && $itinerary->hero_image_path !== '', false],
            ['highlights', $itinerary->highlights !== [], false],
            ['long description', $itinerary->long_description !== '', false],
            ['includes', $itinerary->included !== [], false],
            ['excludes', $itinerary->excluded !== [], false],
            ['FAQs', $itinerary->faqs !== [], false],
            ['URL slug', is_string($itinerary->slug) && $itinerary->slug !== '', false],
            ['SEO title', $itinerary->meta_title !== '', false],
            ['SEO description', $itinerary->meta_description !== '', false],
        ];

        $missing = [];
        $blocking = [];

        foreach ($checks as [$label, $present, $isBlocking]) {
            if ($present) {
                continue;
            }

            $missing[] = $label;

            if ($isBlocking) {
                $blocking[] = $label;
            }
        }

        $pct = (int) round(((count($checks) - count($missing)) / count($checks)) * 100);

        return new self($pct, $missing, $blocking);
    }

    /**
     * @return array{pct: int, missing: list<string>, blocking: list<string>}
     */
    public function toArray(): array
    {
        return [
            'pct' => $this->pct,
            'missing' => $this->missing,
            'blocking' => $this->blocking,
        ];
    }
}
