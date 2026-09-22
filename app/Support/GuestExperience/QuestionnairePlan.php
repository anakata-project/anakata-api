<?php

declare(strict_types=1);

namespace App\Support\GuestExperience;

use App\Enums\DeliveryKind;
use App\Models\Booking;
use App\Models\Guest;
use App\Support\Documents\Recipients;

final class QuestionnairePlan
{
    public function __construct(private readonly Recipients $recipients) {}

    /**
     * @return list<QuestionnaireDispatch>
     */
    public function dispatches(Booking $booking): array
    {
        $booking->loadMissing(['contact', 'group.coordinator', 'guests']);
        $reference = $booking->displayReference();
        $dispatches = [];
        $unaddressed = [];

        foreach ($this->passengers($booking) as $guest) {
            $email = $this->recipients->usableAddress($guest->email);

            if ($email === null) {
                $unaddressed[] = $guest->id;

                continue;
            }

            $dispatches[] = new QuestionnaireDispatch(
                key: 'questionnaire:'.$booking->id.':'.$guest->id,
                label: 'questionnaire for '.$reference.' to '.$email,
                to: [$email],
                blockedReason: null,
                coveredGuestIds: [$guest->id],
                guestId: $guest->id,
            );
        }

        if ($unaddressed !== []) {
            $lead = $this->recipients->resolve($booking, DeliveryKind::Questionnaire);
            $dispatches[] = new QuestionnaireDispatch(
                key: 'questionnaire:'.$booking->id.':lead',
                label: 'questionnaire for '.$reference.' to '.($lead->to[0] ?? 'lead'),
                to: $lead->to,
                blockedReason: $lead->usable() ? null : $lead->blockedReason,
                coveredGuestIds: $unaddressed,
                guestId: null,
            );
        }

        return $dispatches;
    }

    /**
     * Named passengers, plus anyone who already has an email.
     *
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
