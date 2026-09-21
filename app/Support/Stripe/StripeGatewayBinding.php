<?php

declare(strict_types=1);

namespace App\Support\Stripe;

use Illuminate\Contracts\Foundation\Application;

final class StripeGatewayBinding
{
    public static function usesFake(?Application $app = null): bool
    {
        $app ??= app();

        if (! $app->environment(['local', 'testing'])) {
            return false;
        }

        return trim((string) $app['config']->get('services.stripe.secret')) === '';
    }
}
