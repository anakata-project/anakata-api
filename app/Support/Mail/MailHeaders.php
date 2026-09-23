<?php

declare(strict_types=1);

namespace App\Support\Mail;

final class MailHeaders
{
    /**
     * @param  array<mixed>  $headers
     */
    public static function value(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $header) {
            if (is_string($key) && strcasecmp($key, $name) === 0) {
                return self::stringify($header);
            }

            if (! is_array($header)) {
                continue;
            }

            $headerName = $header['Name'] ?? $header['name'] ?? null;

            if (! is_string($headerName) || strcasecmp($headerName, $name) !== 0) {
                continue;
            }

            return self::stringify($header['Value'] ?? $header['value'] ?? null);
        }

        return null;
    }

    private static function stringify(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (! is_array($value)) {
            return null;
        }

        $parts = [];

        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $parts[] = trim($item);
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode(' ', $parts);
    }
}
