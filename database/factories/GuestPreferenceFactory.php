<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PreferenceSource;
use App\Models\Guest;
use App\Models\GuestPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestPreference>
 */
class GuestPreferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guest_id' => Guest::factory(),
            'version' => 1,
            'answers' => [],
            'accessibility' => null,
            'emergency_contact' => null,
            'source' => PreferenceSource::Staff,
            'recorded_by' => null,
            'answered_at' => now(),
            'purged_at' => null,
        ];
    }
}
