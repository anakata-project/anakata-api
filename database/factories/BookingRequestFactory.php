<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\HoldRule;
use App\Enums\PreferredChannel;
use App\Models\Booking;
use App\Models\BookingRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingRequest>
 */
class BookingRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $submitted = now();

        return [
            'booking_id' => Booking::factory()->state([
                'reference' => null,
                'request_reference' => 'ANK-R-2026-'.str_pad((string) fake()->unique()->numberBetween(100, 999), 4, '0', STR_PAD_LEFT),
                'status' => BookingStatus::Requested,
            ]),
            'preferred_channel' => PreferredChannel::Email,
            'travel_advisor' => false,
            'notes' => null,
            'submitted_at' => $submitted,
            'sla_due_at' => $submitted->copy()->addHours(24),
            'hold_rule' => HoldRule::LongLead,
            'hold_expired_at' => null,
        ];
    }
}
