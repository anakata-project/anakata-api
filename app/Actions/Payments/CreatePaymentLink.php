<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Action;
use App\Models\Booking;
use App\Models\PaymentLink;
use App\Models\User;
use App\Support\History\History;
use App\Support\Payments\PaymentHistory;

final class CreatePaymentLink extends Action
{
    public function __construct(private readonly IssuePaymentLink $issuer) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Booking $booking, array $data, User $actor): PaymentLink
    {
        return $this->transaction(function () use ($booking, $data, $actor): PaymentLink {
            $link = $this->issuer->handle($booking, $data);

            History::record($booking, PaymentHistory::LINK_CREATED, after: PaymentHistory::linkPayload($link), actor: $actor);

            return $link;
        });
    }
}
