<?php

declare(strict_types=1);

namespace App\Support\Itineraries;

use InvalidArgumentException;

/**
 * Prototype GRADS. The key is stored; the CSS is returned by the resource.
 */
final class Gradients
{
    /** @var array<string, string> */
    public const ALL = [
        'Western (slate)' => 'linear-gradient(135deg,#1B2832 0%,#2A3A42 45%,#33413F 100%)',
        'Northern (forest)' => 'linear-gradient(135deg,#141B17 0%,#37453D 55%,#585940 100%)',
        'Festive (amber)' => 'linear-gradient(135deg,#202B26 0%,#2A362F 50%,#A97C4B 100%)',
    ];

    public const DEFAULT_KEY = 'Western (slate)';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    public static function css(string $key): string
    {
        if (! array_key_exists($key, self::ALL)) {
            throw new InvalidArgumentException("Unknown itinerary gradient [{$key}].");
        }

        return self::ALL[$key];
    }

    public static function keyFromCss(string $css): string
    {
        $key = array_search($css, self::ALL, true);

        if (! is_string($key)) {
            throw new InvalidArgumentException("Unknown itinerary gradient CSS [{$css}].");
        }

        return $key;
    }
}
