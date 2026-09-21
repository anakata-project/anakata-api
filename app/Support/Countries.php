<?php

declare(strict_types=1);

namespace App\Support;

final class Countries
{
    /** @var array<string, string>|null */
    private static ?array $names = null;

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::$names ??= require __DIR__.'/Countries/iso3166.php';
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function isValid(string $code): bool
    {
        return isset(self::all()[strtoupper($code)]);
    }

    public static function name(string $code): string
    {
        $upper = strtoupper($code);

        return self::all()[$upper] ?? $upper;
    }
}
