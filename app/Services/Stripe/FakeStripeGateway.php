<?php

declare(strict_types=1);

namespace App\Services\Stripe;

use App\Enums\PaymentKind;
use App\Exceptions\InvalidStripeSignature;
use App\Models\Booking;
use App\Support\Stripe\StripeWebhookSignature;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class FakeStripeGateway implements StripeGateway
{
    /** @var array<string, CreatedPaymentLink> */
    public array $links = [];

    /** @var array<string, bool> */
    public array $active = [];

    /** @var list<StripeCharge> */
    public array $charges = [];

    public int $linkSequence = 0;

    public function createPaymentLink(Booking $booking, PaymentKind $kind, int $amountUsd): CreatedPaymentLink
    {
        $this->linkSequence++;
        $id = 'plink_test_'.str_pad((string) $this->linkSequence, 3, '0', STR_PAD_LEFT);
        $link = new CreatedPaymentLink($id, 'https://buy.stripe.com/test/'.$id);
        $this->links[$id] = $link;
        $this->active[$id] = true;

        return $link;
    }

    public function deactivatePaymentLink(string $stripeId): void
    {
        if (! isset($this->links[$stripeId])) {
            throw new RuntimeException('Unknown payment link '.$stripeId);
        }

        $this->active[$stripeId] = false;
    }

    public function listCharges(CarbonInterface $fromUtc, CarbonInterface $toUtc): array
    {
        $from = $fromUtc->getTimestamp();
        $to = $toUtc->getTimestamp();

        return array_values(array_filter(
            $this->charges,
            fn (StripeCharge $charge): bool => $charge->createdAt->getTimestamp() >= $from
                && $charge->createdAt->getTimestamp() <= $to,
        ));
    }

    public function retrieveCharge(string $stripeId): StripeCharge
    {
        foreach ($this->charges as $charge) {
            if ($charge->id === $stripeId) {
                return $charge;
            }
        }

        throw new InvalidArgumentException('Unknown Stripe charge '.$stripeId);
    }

    public function verifyWebhook(string $payload, string $signature): VerifiedStripeEvent
    {
        StripeWebhookSignature::verify(
            $payload,
            $signature,
            (string) config('services.stripe.webhook_secret'),
        );

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidStripeSignature;
        }

        $id = $decoded['id'] ?? null;
        $type = $decoded['type'] ?? null;

        if (! is_string($id) || $id === '' || ! is_string($type) || $type === '') {
            throw new InvalidStripeSignature;
        }

        return new VerifiedStripeEvent($id, $type, $decoded);
    }

    public function seedCharge(StripeCharge $charge): void
    {
        $this->charges[] = $charge;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array{payload: string, signature: string}
     */
    public static function signedEvent(array $event, ?int $timestamp = null): array
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $signature = StripeWebhookSignature::sign(
            $payload,
            (string) config('services.stripe.webhook_secret'),
            $timestamp ?? time(),
        );

        return [
            'payload' => $payload,
            'signature' => $signature,
        ];
    }

    public static function charge(
        string $id,
        string $paymentIntentId,
        int $amountUsd,
        CarbonImmutable $createdAt,
        string $description = '',
    ): StripeCharge {
        return new StripeCharge($id, $paymentIntentId, $amountUsd, $createdAt, $description);
    }
}
