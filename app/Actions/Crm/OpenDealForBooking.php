<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\BookingType;
use App\Enums\DealStage;
use App\Enums\DealType;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Group;
use App\Support\History\History;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

final class OpenDealForBooking extends Action
{
    public function handle(Booking $booking): Deal
    {
        try {
            return $this->transaction(fn (): Deal => $this->open($booking));
        } catch (UniqueConstraintViolationException $exception) {
            $found = $this->findExisting($booking);

            if ($found instanceof Deal) {
                return $found;
            }

            throw $exception;
        }
    }

    private function open(Booking $booking): Deal
    {
        $booking->refresh();
        $existing = $this->findExisting($booking);

        if ($existing instanceof Deal) {
            return $existing;
        }

        $contact = Contact::resolveIdentity($booking->contact_id) ?? $booking->contact;
        $open = Deal::query()
            ->where('contact_id', $contact->id)
            ->whereNull('booking_id')
            ->whereNull('group_id')
            ->whereIn('stage', array_map(
                fn (DealStage $stage): string => $stage->value,
                DealStage::open(),
            ))
            ->orderBy('id')
            ->get();

        if ($open->count() === 1) {
            /** @var Deal $deal */
            $deal = $open->first();

            return $this->bind($deal, $booking);
        }

        return $this->createBound($contact, $booking);
    }

    private function findExisting(Booking $booking): ?Deal
    {
        if ($booking->group_id !== null) {
            return Deal::query()->where('group_id', $booking->group_id)->first();
        }

        return Deal::query()->where('booking_id', $booking->id)->first();
    }

    private function bind(Deal $deal, Booking $booking): Deal
    {
        $from = $deal->stage?->value;
        $deal->forceFill([
            'booking_id' => $booking->group_id === null ? $booking->id : null,
            'group_id' => $booking->group_id,
            'stage' => null,
            'type' => self::typeFor($booking),
            'owner_id' => $deal->owner_id ?? $booking->owner_id,
        ])->save();

        History::record($deal, 'deal.bound', before: [
            'stage' => $from,
        ], after: [
            'booking_id' => $deal->booking_id,
            'group_id' => $deal->group_id,
            'reference' => self::reference($booking),
        ], system: true);

        return $deal->refresh();
    }

    private function createBound(Contact $contact, Booking $booking): Deal
    {
        $deal = Deal::query()->create([
            'contact_id' => $contact->id,
            'owner_id' => $booking->owner_id,
            'title' => $contact->name,
            'type' => self::typeFor($booking),
            'stage' => null,
            'stage_entered_at' => Carbon::now(),
            'booking_id' => $booking->group_id === null ? $booking->id : null,
            'group_id' => $booking->group_id,
        ]);

        History::record($deal, 'deal.created', after: [
            'title' => $deal->title,
            'type' => $deal->type->value,
            'bound' => true,
        ], system: true);

        History::record($deal, 'deal.bound', after: [
            'booking_id' => $deal->booking_id,
            'group_id' => $deal->group_id,
            'reference' => self::reference($booking),
        ], system: true);

        return $deal->refresh();
    }

    public static function typeFor(Booking $booking): DealType
    {
        if ($booking->type === BookingType::Charter) {
            return DealType::Charter;
        }

        if ($booking->agency_id !== null) {
            return DealType::Agency;
        }

        if ($booking->group_id !== null) {
            return DealType::Group;
        }

        return DealType::Fit;
    }

    public static function reference(Booking $booking): string
    {
        if (is_string($booking->reference) && $booking->reference !== '') {
            return $booking->reference;
        }

        if (is_string($booking->request_reference) && $booking->request_reference !== '') {
            return $booking->request_reference;
        }

        $group = $booking->group_id !== null ? Group::query()->find($booking->group_id) : null;

        return $group instanceof Group ? $group->reference : 'the booking';
    }
}
