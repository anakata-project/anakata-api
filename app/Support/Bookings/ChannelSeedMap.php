<?php

declare(strict_types=1);

namespace App\Support\Bookings;

use App\Enums\ChannelOfOrigin;
use App\Enums\MainChannel;
use InvalidArgumentException;

final class ChannelSeedMap
{
    /**
     * @return array{main: MainChannel, origin: ChannelOfOrigin}
     */
    public static function fromPrototype(string $chan): array
    {
        return match ($chan) {
            'WEB_DIRECT' => [
                'main' => MainChannel::D2C,
                'origin' => ChannelOfOrigin::HotelBookingEngine,
            ],
            'INBOUND' => [
                'main' => MainChannel::D2C,
                'origin' => ChannelOfOrigin::Email,
            ],
            'AGENCY' => [
                'main' => MainChannel::B2BTravelAdvisor,
                'origin' => ChannelOfOrigin::TravelAdvisor,
            ],
            'CHARTER_DIRECT' => [
                'main' => MainChannel::D2C,
                'origin' => ChannelOfOrigin::Email,
            ],
            default => throw new InvalidArgumentException('Unknown seed channel ['.$chan.'].'),
        };
    }
}
