<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payments\SettleGatewayPayment;
use App\Enums\PaymentLinkStatus;
use App\Http\Controllers\StripeWebhookController;
use App\Jobs\ProcessStripeEvent;
use App\Models\Booking;
use App\Models\PaymentLink;
use App\Models\StripeEvent;
use App\Services\Stripe\FakeStripeGateway;
use App\Services\Stripe\StripeGateway;
use App\Support\Stripe\StripeMoney;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

final class ReplayStripeCheckoutCommand extends Command
{
    protected $signature = 'anakata:replay-stripe-checkout {reference}';

    protected $description = 'Replay checkout.session.completed for an open payment link (local/testing only)';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('anakata:replay-stripe-checkout only runs in local and testing.');

            return self::FAILURE;
        }

        $gateway = app(StripeGateway::class);

        $reference = (string) $this->argument('reference');
        $booking = Booking::query()
            ->where('reference', $reference)
            ->orWhere('request_reference', $reference)
            ->first();

        if (! $booking instanceof Booking) {
            $this->error('No booking with reference '.$reference.'.');

            return self::FAILURE;
        }

        $link = PaymentLink::query()
            ->where('booking_id', $booking->id)
            ->where('status', PaymentLinkStatus::Open)
            ->orderBy('id')
            ->first();

        if (! $link instanceof PaymentLink) {
            $this->error('No OPEN payment link on '.$reference.'.');

            return self::FAILURE;
        }

        $eventId = 'evt_replay_'.$link->id;
        $event = [
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_replay_'.$link->id,
                    'payment_intent' => 'pi_replay_'.$link->id,
                    'payment_link' => $link->stripe_id,
                    'amount_total' => StripeMoney::toCents($link->amount),
                    'metadata' => [
                        'booking_id' => (string) $booking->id,
                        'booking_reference' => (string) $booking->displayReference(),
                        'kind' => $link->kind->value,
                    ],
                ],
            ],
        ];

        $signed = FakeStripeGateway::signedEvent($event);
        $request = Request::create(
            '/api/stripe/webhook',
            'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signed['signature'],
            ],
            content: $signed['payload'],
        );

        $controller = app(StripeWebhookController::class);
        $controller($request, $gateway);
        $controller($request, $gateway);

        $stored = StripeEvent::query()->where('stripe_event_id', $eventId)->first();

        if ($stored instanceof StripeEvent && $stored->processed_at === null) {
            (new ProcessStripeEvent($eventId))->handle(app(SettleGatewayPayment::class));
        }

        $this->info('Replayed checkout.session.completed twice for '.$reference.' ('.$link->stripe_id.').');

        return self::SUCCESS;
    }
}
