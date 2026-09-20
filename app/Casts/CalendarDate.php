<?php

declare(strict_types=1);

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Date-only field: stored and exchanged as Y-m-d, never shifted by a time zone.
 *
 * Eloquent's addCastAttributesToArray() runs serializeDate() (Iso::utc) before
 * this serialize() when get() returns a DateTimeInterface. serialize() therefore
 * reads the raw attribute and overwrites the timestamp with Y-m-d.
 *
 * @implements CastsAttributes<CarbonImmutable|null, DateTimeInterface|string|null>
 */
final class CalendarDate implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $this->calendarString($value));

        if (! $date instanceof CarbonImmutable) {
            throw new InvalidArgumentException('Invalid calendar date ['.$this->calendarString($value).'].');
        }

        return $date;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->calendarString($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $raw = $attributes[$key] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        return $this->calendarString($raw);
    }

    private function calendarString(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException('Calendar dates must be Y-m-d strings or DateTimeInterface.');
        }

        $string = is_int($value) ? (string) $value : $value;

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $string, $matches) !== 1) {
            throw new InvalidArgumentException("Invalid calendar date [{$string}].");
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $matches[1]);

        if (! $date instanceof CarbonImmutable) {
            throw new InvalidArgumentException("Invalid calendar date [{$string}].");
        }

        return $date->format('Y-m-d');
    }
}
