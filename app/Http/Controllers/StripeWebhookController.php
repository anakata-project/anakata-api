<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\InvalidStripeSignature;
use App\Jobs\ProcessStripeEvent;
use App\Services\Stripe\StripeGateway;
use App\Support\Stripe\RedactStripePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeGateway $gateway): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');

        try {
            $event = $gateway->verifyWebhook($payload, $signature);
        } catch (InvalidStripeSignature) {
            Log::warning('Stripe webhook rejected: bad signature.');

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        DB::transaction(function () use ($event): void {
            $inserted = DB::table('stripe_events')->insertOrIgnore([
                'stripe_event_id' => $event->id,
                'type' => $event->type,
                'payload' => json_encode(RedactStripePayload::handle($event->payload), JSON_THROW_ON_ERROR),
                'received_at' => Carbon::now(),
            ]);

            if ($inserted === 0) {
                return;
            }

            ProcessStripeEvent::dispatch($event->id)->afterCommit();
        });

        return response()->json(['ok' => true]);
    }
}
