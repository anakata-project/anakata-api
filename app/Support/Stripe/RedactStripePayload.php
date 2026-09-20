<?php

declare(strict_types=1);

namespace App\Support\Stripe;

final class RedactStripePayload
{
    /** @var list<string> */
    private const CARD_KEYS = [
        'last4',
        'number',
        'cvc',
        'iin',
        'fingerprint',
    ];

    /**
     * @param  array<mixed>  $payload
     * @return array<mixed>
     */
    public static function handle(array $payload): array
    {
        $redacted = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array($key, self::CARD_KEYS, true)) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = is_array($value)
                ? self::handle($value)
                : $value;
        }

        return $redacted;
    }
}
