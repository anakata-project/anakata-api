<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConsentDocument;
use App\Enums\ConsentSource;
use App\Models\Booking;
use App\Models\Consent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consent>
 */
class ConsentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'document' => ConsentDocument::Terms,
            'version' => 'v2026.1 (text pending LEG-001)',
            'accepted_at' => now(),
            'ip' => null,
            'source' => ConsentSource::Staff,
            'recorded_by' => null,
            'how_obtained' => 'signed PDF by email',
            'withdrawn' => false,
        ];
    }
}
