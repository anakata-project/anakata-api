<?php

declare(strict_types=1);

namespace App\Support\Guests;

use App\Models\Booking;
use App\Models\Guest;
use App\Services\Config\CurrentConfig;

final class ApplyPng
{
    public function __construct(private CurrentConfig $config) {}

    public function toGuest(Guest $guest, Booking $booking): void
    {
        $booking->loadMissing('departure');

        $computed = PngCategory::for(
            $guest->dob,
            $guest->nationality,
            (bool) $guest->ecuador_resident,
            $booking->departure->date,
            $this->config->engineSettings(),
        );

        $guest->png_category = $computed['category'];
        $guest->png_fee = $computed['fee'];
    }

    public function toBooking(Booking $booking): void
    {
        $booking->loadMissing(['departure', 'guests']);

        foreach ($booking->guests as $guest) {
            $this->toGuest($guest, $booking);
            $guest->save();
        }
    }
}
