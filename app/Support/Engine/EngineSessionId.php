<?php

declare(strict_types=1);

namespace App\Support\Engine;

use Closure;

final class EngineSessionId
{
    /**
     * @return list<mixed>
     */
    public static function rules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'sometimes',
            'nullable',
            'string',
            'min:16',
            'max:64',
            'regex:/^[A-Za-z0-9_-]+$/',
            self::notAnIp(),
        ];
    }

    private static function notAnIp(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
                $fail('The '.$attribute.' must not be derived from an IP address.');
            }
        };
    }
}
