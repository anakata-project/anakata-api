<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Checkout\FallBackOnlineDeposit;
use App\Enums\CheckoutPath;
use App\Enums\CheckoutSessionStatus;
use App\Models\CheckoutSession;
use App\Services\Stripe\StripeGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ExpireStripeCheckoutsCommand extends Command
{
    protected $signature = 'engine:expire-stripe-checkouts';

    protected $description = 'Remove the online-deposit advantage when Stripe reports the checkout session expired';

    public function handle(StripeGateway $stripe, FallBackOnlineDeposit $fallback): int
    {
        $candidates = CheckoutSession::query()
            ->where('status', CheckoutSessionStatus::Submitted)
            ->where('path', CheckoutPath::PayDeposit)
            ->whereNotNull('stripe_checkout_session_id')
            ->whereNotNull('stripe_expires_at')
            ->where('stripe_expires_at', '<', now())
            ->get();

        $expired = 0;

        foreach ($candidates as $session) {
            try {
                $retrieved = $stripe->retrieveCheckoutSession((string) $session->stripe_checkout_session_id);
            } catch (Throwable $exception) {
                Log::warning('Could not retrieve Stripe checkout session.', [
                    'checkout_session_id' => $session->id,
                    'stripe_id' => $session->stripe_checkout_session_id,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            if ($retrieved->status === 'complete') {
                continue;
            }

            if ($retrieved->status !== 'expired') {
                continue;
            }

            $expired += $fallback->handle($session);
        }

        if ($expired > 0) {
            Log::info('Removed online-deposit advantage after Stripe expiry.', ['count' => $expired]);
        }

        return self::SUCCESS;
    }
}
