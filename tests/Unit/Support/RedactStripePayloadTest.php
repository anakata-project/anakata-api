<?php

declare(strict_types=1);

use App\Support\Stripe\RedactStripePayload;

test('cardholder fields are redacted before a webhook payload is stored', function (): void {
    $redacted = RedactStripePayload::handle([
        'data' => [
            'object' => [
                'id' => 'ch_1',
                'payment_method_details' => [
                    'card' => [
                        'last4' => '4242',
                        'fingerprint' => 'abc',
                        'brand' => 'visa',
                    ],
                ],
            ],
        ],
    ]);

    expect($redacted['data']['object']['payment_method_details']['card']['last4'])->toBe('[redacted]');
    expect($redacted['data']['object']['payment_method_details']['card']['fingerprint'])->toBe('[redacted]');
    expect($redacted['data']['object']['payment_method_details']['card']['brand'])->toBe('visa');
    expect($redacted['data']['object']['id'])->toBe('ch_1');
});
