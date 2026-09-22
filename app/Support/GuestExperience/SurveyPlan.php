<?php

declare(strict_types=1);

namespace App\Support\GuestExperience;

use App\Enums\DeliveryKind;
use App\Models\Booking;
use App\Models\Guest;
use App\Support\Documents\Recipients;

final class SurveyPlan
{
    public function __construct(private readonly Recipients $recipients) {}

    /**
     * @return list<SurveyDispatch>
     */
    public function dispatches(Booking $booking): array
    {
        $booking->loadMissing(['contact', 'group.coordinator', 'guests']);
        $dispatches = [];
        $unaddressed = [];

        foreach ($this->passengers($booking) as $guest) {
            $email = $this->recipients->usableAddress($guest->email);

            if ($email === null) {
                $unaddressed[] = $guest->id;

                continue;
            }

            $dispatches[] = new SurveyDispatch(
                key: 'survey:'.$guest->id,
                to: [$email],
                blockedReason: null,
                coveredGuestIds: [$guest->id],
                guestId: $guest->id,
            );
        }

        if ($unaddressed !== []) {
            $lead = $this->recipients->resolve($booking, DeliveryKind::Survey);
            $dispatches[] = new SurveyDispatch(
                key: 'survey:'.$booking->id.':lead',
                to: $lead->to,
                blockedReason: $lead->usable() ? null : $lead->blockedReason,
                coveredGuestIds: $unaddressed,
                guestId: null,
            );
        }

        return $dispatches;
    }

    /**
     * @return list<Guest>
     */
    private function passengers(Booking $booking): array
    {
        return $booking->guests
            ->filter(function (Guest $guest): bool {
                return trim($guest->first_name.$guest->last_name) !== ''
                    || $this->recipients->usableAddress($guest->email) !== null;
            })
            ->values()
            ->all();
    }
}
