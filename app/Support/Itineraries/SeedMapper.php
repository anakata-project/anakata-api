<?php

declare(strict_types=1);

namespace App\Support\Itineraries;

use App\Enums\ItineraryStatus;

/**
 * Maps a prototype / seed-data.json itinerary row onto itineraries columns.
 */
final class SeedMapper
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function fromPrototype(array $row): array
    {
        $image = is_string($row['img'] ?? null) ? $row['img'] : '';

        return [
            'code' => (string) $row['k'],
            'name' => (string) $row['name'],
            'status' => ItineraryStatus::from((string) $row['status']),
            'sort_order' => (int) $row['order'],
            'festive' => (bool) $row['festive'],
            'days' => (int) $row['nDays'],
            'nights' => (int) $row['nights'],
            'embark' => (string) $row['embark'],
            'disembark' => (string) $row['disembark'],
            'tagline' => (string) $row['tag'],
            'hero_image_path' => $image === '' ? null : $image,
            'hero_alt' => (string) $row['alt'],
            'fallback_gradient' => Gradients::keyFromCss((string) $row['grad']),
            'card_description' => (string) $row['desc'],
            'overview' => (string) $row['overview'],
            'long_description' => (string) $row['long'],
            'highlights' => $row['hi'],
            'chips' => $row['chips'],
            'facts' => $row['facts'],
            'day_plan' => $row['plan'],
            'included' => $row['inc'],
            'excluded' => $row['exc'],
            'faqs' => $row['faqs'],
            'slug' => self::nullableString($row['slug'] ?? null),
            'meta_title' => (string) ($row['metaT'] ?? ''),
            'meta_description' => (string) ($row['metaD'] ?? ''),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
