<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Encrypts a string with SENSITIVE_DATA_KEY. Never falls back to APP_KEY.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class SensitiveEncrypted implements CastsAttributes
{
    private static ?Encrypter $encrypter = null;

    public static function flushEncrypter(): void
    {
        self::$encrypter = null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new RuntimeException('Sensitive ciphertext must be a string.');
        }

        return self::encrypter()->decryptString($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::encrypter()->encryptString($value);
    }

    private static function encrypter(): Encrypter
    {
        return self::$encrypter ??= self::makeEncrypter();
    }

    private static function makeEncrypter(): Encrypter
    {
        $key = config('sensitive.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException(
                'SENSITIVE_DATA_KEY is not set. Generate a key with `php artisan key:generate --show` and set SENSITIVE_DATA_KEY — never reuse APP_KEY.',
            );
        }

        $parsed = self::parseKey($key);
        $cipher = is_string(config('app.cipher')) ? config('app.cipher') : 'AES-256-CBC';

        if (! Encrypter::supported($parsed, $cipher)) {
            throw new RuntimeException('SENSITIVE_DATA_KEY is not a valid '.$cipher.' key.');
        }

        return new Encrypter($parsed, $cipher);
    }

    private static function parseKey(string $key): string
    {
        if (! str_starts_with($key, 'base64:')) {
            return $key;
        }

        $decoded = base64_decode(substr($key, 7), true);

        if ($decoded === false) {
            throw new RuntimeException('SENSITIVE_DATA_KEY is not valid base64.');
        }

        return $decoded;
    }
}
