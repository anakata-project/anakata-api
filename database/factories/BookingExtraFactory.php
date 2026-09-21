<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingExtra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingExtra>
 */
class BookingExtraFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'code' => 'FLT',
            'name' => 'Domestic flights GYE/UIO ↔ SCY (round-trip)',
            'unit' => 'per person',
            'qty' => 1,
            'rate_usd' => 420,
            'note' => null,
        ];
    }
}
