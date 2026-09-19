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

            $leftAssoc = self::isAssociative($left);
            $rightAssoc = self::isAssociative($right);

            if (($leftAssoc || $rightAssoc)
                && ($left === null || $leftAssoc)
                && ($right === null || $rightAssoc)
            ) {
                /** @var array<string, mixed> $leftWalk */
                $leftWalk = $leftAssoc ? $left : [];
                /** @var array<string, mixed> $rightWalk */
                $rightWalk = $rightAssoc ? $right : [];
                $changes = [...$changes, ...self::walk($leftWalk, $rightWalk, $labels, $path)];

                continue;
            }

            if (! self::equal($left, $right)) {
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

    /**
     * Canonical equality: associative keys are sorted, lists keep order.
     */
    public static function equal(mixed $left, mixed $right): bool
    {
        return json_encode(self::canonicalise($left)) === json_encode(self::canonicalise($right));
    }

    /**
     * Leaf paths using the same walk as compare() — associative objects recurse, lists are one leaf.
     *
     * @param  array<string, mixed>  $tree
     * @return list<string>
     */
    public static function leafPaths(array $tree, string $prefix = ''): array
    {
        $paths = [];

        foreach ($tree as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value) && $value !== [] && ! array_is_list($value)) {
                $paths = [...$paths, ...self::leafPaths($value, $path)];

                continue;
            }

            $paths[] = $path;
        }

        return $paths;
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
