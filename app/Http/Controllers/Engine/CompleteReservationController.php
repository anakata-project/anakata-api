<?php

declare(strict_types=1);

namespace App\Http\Controllers\Engine;

use App\Actions\Bookings\UpdateBookingBilling;
use App\Actions\Complete\ResolveCompleteAccessToken;
use App\Actions\Consents\RecordConsent;
use App\Actions\Contacts\StitchEngineIdentity;
use App\Actions\Guests\UpdateGuest;
use App\Enums\ConsentSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engine\RecordCompleteDeclarationsRequest;
use App\Http\Requests\Engine\UpdateCompleteBillingRequest;
use App\Http\Requests\Engine\UpdateCompleteGuestRequest;
use App\Http\Resources\Engine\CompleteReservationResource;
use App\Models\Booking;
use App\Models\Guest;
use App\Support\Complete\CompleteAccess;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;

final class CompleteReservationController extends Controller
{
    public function __construct(
        private readonly ResolveCompleteAccessToken $resolve,
        private readonly StitchEngineIdentity $identity,
    ) {}

    #[DocumentedResponse(status: 200, type: CompleteReservationResource::class)]
    public function show(string $token): CompleteReservationResource
    {
        return new CompleteReservationResource($this->booking($token));
    }

    #[DocumentedResponse(status: 200, type: CompleteReservationResource::class)]
    public function updateBilling(
        UpdateCompleteBillingRequest $request,
        string $token,
        UpdateBookingBilling $action,
    ): CompleteReservationResource {
        $booking = $this->booking($token);
        $validated = $request->validated();
        $updated = $action->handle(
            $booking,
            $validated,
            actorLabel: CompleteAccess::ACTOR_LABEL,
        );
        $this->stitch($updated, $validated['session_id'] ?? null);

        return new CompleteReservationResource($updated);
    }

    #[DocumentedResponse(status: 200, type: CompleteReservationResource::class)]
    public function updateGuest(
        UpdateCompleteGuestRequest $request,
        string $token,
        int $guest,
        UpdateGuest $action,
    ): CompleteReservationResource {
        $booking = $this->booking($token);
        $model = $this->guestInScope($booking, $guest);
        $validated = $request->validated();

        $action->handle(
            $model,
            $validated,
            actorLabel: CompleteAccess::ACTOR_LABEL,
        );

        $fresh = $booking->fresh() ?? $booking;
        $this->stitch($fresh, $validated['session_id'] ?? null);

        return new CompleteReservationResource($fresh);
    }

    #[DocumentedResponse(status: 200, type: CompleteReservationResource::class)]
    public function recordDeclarations(
        RecordCompleteDeclarationsRequest $request,
        string $token,
        RecordConsent $action,
    ): CompleteReservationResource {
        $booking = $this->booking($token);

        foreach ($request->documents() as $document) {
            $action->handle(
                $booking,
                $document,
                ConsentSource::PaymentLink,
                ip: $request->ip(),
                actorLabel: CompleteAccess::ACTOR_LABEL,
            );
        }

        foreach ($request->withdrawn() as $document) {
            $action->handle(
                $booking,
                $document,
                ConsentSource::PaymentLink,
                ip: $request->ip(),
                actorLabel: CompleteAccess::ACTOR_LABEL,
                withdrawn: true,
            );
        }

        $fresh = $booking->fresh() ?? $booking;
        $this->stitch($fresh, $request->validated('session_id'));

        return new CompleteReservationResource($fresh);
    }

    private function stitch(Booking $booking, mixed $sessionId): void
    {
        if (! is_string($sessionId) || $sessionId === '') {
            return;
        }

        $contact = $booking->contact;

        $this->identity->handle($contact, $sessionId);
    }

    private function booking(string $token): Booking
    {
        $row = $this->resolve->handle($token);

        return $row->booking;
    }

    private function guestInScope(Booking $booking, int $guestId): Guest
    {
        $guest = Guest::query()->with('booking')->find($guestId);

        if (! $guest instanceof Guest) {
            CompleteAccess::abortNotFound();
        }

        if ((int) $guest->booking_id === (int) $booking->id) {
            return $guest;
        }

        if ($booking->group_id !== null
            && $guest->booking->group_id !== null
            && (int) $guest->booking->group_id === (int) $booking->group_id
        ) {
            return $guest;
        }

        CompleteAccess::abortNotFound();
    }
}
