<?php

declare(strict_types=1);

namespace App\Support\Stripe;

use App\Exceptions\InvalidStripeSignature;

final class StripeWebhookSignature
{
    public static function sign(string $payload, string $secret, int $timestamp): string
    {
        $hmac = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return 't='.$timestamp.',v1='.$hmac;
    }

    /**
     * @throws InvalidStripeSignature
     */
    public static function verify(string $payload, string $header, string $secret, int $toleranceSeconds = 300): int
    {
        $parts = [];

        foreach (explode(',', $header) as $item) {
            [$key, $value] = array_pad(explode('=', $item, 2), 2, '');
            $parts[$key][] = $value;
        }

        $timestamp = isset($parts['t'][0]) ? (int) $parts['t'][0] : 0;
        $signatures = $parts['v1'] ?? [];

        if ($timestamp < 1 || $signatures === []) {
            throw new InvalidStripeSignature;
        }

        if (abs(time() - $timestamp) > $toleranceSeconds) {
            throw new InvalidStripeSignature;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return $timestamp;
            }
        }

        throw new InvalidStripeSignature;
    }
}
