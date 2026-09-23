<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\JourneyEnrolmentStatus;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Journey;
use App\Models\JourneyEnrolment;
use App\Models\JourneyStep;
use App\Support\History\History;
use App\Support\Journeys\JourneyClock;
use Illuminate\Support\Carbon;

final class EnrolJourney extends Action
{
    public function __construct(private readonly JourneyClock $clock) {}

    public function handle(Journey $journey, Contact $contact, ?Booking $booking): JourneyEnrolment
    {
        /** @var JourneyEnrolment $enrolment */
        $enrolment = $this->transaction(function () use ($journey, $contact, $booking): JourneyEnrolment {
            $enrolment = JourneyEnrolment::query()->create([
                'journey_id' => $journey->id,
                'contact_id' => $contact->id,
                'booking_id' => $booking?->id,
                'position' => 1,
                'status' => JourneyEnrolmentStatus::Active,
                'enrolled_at' => now(),
            ]);

            $first = $journey->steps()->orderBy('position')->first();
            $enrolment->next_due_at = $first instanceof JourneyStep
                ? Carbon::instance($this->clock->dueAt($enrolment, $first))
                : null;
            $enrolment->save();

            History::record(
                $enrolment,
                'journey.enrolled',
                after: ['journey' => $journey->key, 'booking_id' => $enrolment->booking_id],
                system: true,
            );

            return $enrolment;
        });

        return $enrolment;
    }
}
