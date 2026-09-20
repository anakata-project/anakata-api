<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Action;
use App\Enums\PaymentLinkStatus;
use App\Models\PaymentLink;
use App\Models\User;
use App\Services\Stripe\StripeGateway;
use App\Support\History\History;
use App\Support\Payments\PaymentHistory;
use Illuminate\Validation\ValidationException;

final class CancelPaymentLink extends Action
{
    public function __construct(private readonly StripeGateway $stripe) {}

    public function handle(PaymentLink $link, User $actor): PaymentLink
    {
        return $this->transaction(function () use ($link, $actor): PaymentLink {
            $link = PaymentLink::query()->whereKey($link->getKey())->lockForUpdate()->firstOrFail();

            if ($link->status !== PaymentLinkStatus::Open) {
                throw ValidationException::withMessages([
                    'link' => ['Only an open payment link can be cancelled.'],
                ]);
            }

            $this->stripe->deactivatePaymentLink($link->stripe_id);

            $link->status = PaymentLinkStatus::Cancelled;
            $link->save();

            History::record(
                $link->booking,
                PaymentHistory::LINK_CANCELLED,
                after: PaymentHistory::linkPayload($link),
                actor: $actor,
            );

            return $link;
        });
    }
}
