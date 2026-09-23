<?php

declare(strict_types=1);

namespace App\Enums;

enum JourneySubject: string
{
    case Booking = 'BOOKING';
    case Contact = 'CONTACT';
}
