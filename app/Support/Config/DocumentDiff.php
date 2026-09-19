<?php

declare(strict_types=1);

namespace App\Support\Config;

final class DocumentDiff
{
    /**
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     * @param  array<string, string>  $labels
     * @return list<Change>
     */
    public static function compare(array $from, array $to, array $labels): array
    {
        return self::walk($from, $to, $labels, '');
    }

    /**
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     * @param  array<string, string>  $labels
     * @return list<Change>
     */
    private static function walk(array $from, array $to, array $labels, string $prefix): array
    {
        $changes = [];
        $keys = array_unique([...array_keys($from), ...array_keys($to)]);

        foreach ($keys as $key) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $hasFrom = array_key_exists($key, $from);
            $hasTo = array_key_exists($key, $to);
            $left = $hasFrom ? $from[$key] : null;
            $right = $hasTo ? $to[$key] : null;

            if ($hasFrom && $hasTo && self::isAssociative($left) && self::isAssociative($right)) {
                /** @var array<string, mixed> $left */
                /** @var array<string, mixed> $right */
                $changes = [...$changes, ...self::walk($left, $right, $labels, $path)];

                continue;
            }

            if (! self::same($left, $right)) {
                $changes[] = new Change(
                    $path,
                    $labels[$path] ?? $path,
                    $left,
                    $right,
                );
            }
        }

        return $changes;
    }

    private static function isAssociative(mixed $value): bool
    {
        return is_array($value) && $value !== [] && ! array_is_list($value);
    }

    private static function same(mixed $left, mixed $right): bool
    {
        return json_encode(self::canonicalise($left)) === json_encode(self::canonicalise($right));
    }

    private static function canonicalise(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::canonicalise(...), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalise($item);
        }

        return $value;
    }
}
