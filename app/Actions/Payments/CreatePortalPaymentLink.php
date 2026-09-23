<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Action;
use App\Enums\PaymentKind;
use App\Models\AgencyUser;
use App\Models\Booking;
use App\Models\PaymentLink;
use App\Support\History\History;
use App\Support\Payments\PaymentHistory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class CreatePortalPaymentLink extends Action
{
    public function __construct(private readonly IssuePaymentLink $issuer) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Booking $booking, array $data, AgencyUser $actor): PaymentLink
    {
        $driver = Auth::getDefaultDriver();
        Auth::shouldUse('web');

        try {
            return $this->transaction(function () use ($booking, $data, $actor): PaymentLink {
                $this->guardAgency($booking, $actor);

                $kind = $data['kind'] instanceof PaymentKind
                    ? $data['kind']
                    : PaymentKind::from((string) $data['kind']);

                if (! in_array($kind, [PaymentKind::Deposit, PaymentKind::Balance], true)) {
                    throw ValidationException::withMessages([
                        'kind' => ['The selected kind is invalid.'],
                    ]);
                }

                $link = $this->issuer->handle($booking, ['kind' => $kind]);

                History::record(
                    $booking,
                    PaymentHistory::LINK_CREATED,
                    after: PaymentHistory::linkPayload($link),
                    actorLabel: $actor->name.' via portal',
                    extraContext: [
                        'agency_user_id' => $actor->id,
                    ],
                );

                return $link;
            });
        } finally {
            Auth::shouldUse($driver);
        }
    }

    private function guardAgency(Booking $booking, AgencyUser $actor): void
    {
        if ($booking->agency_id === null || (int) $booking->agency_id !== (int) $actor->agency_id) {
            throw new AuthorizationException('This booking is not available to your agency.');
        }
    }
}
