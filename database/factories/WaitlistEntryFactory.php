<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CabinCategory;
use App\Enums\WaitlistSource;
use App\Models\Contact;
use App\Models\Departure;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'departure_id' => Departure::factory(),
            'cabin_category' => CabinCategory::Suite,
            'contact_id' => Contact::factory(),
            'adults' => 2,
            'children' => 0,
            'notes' => null,
            'source' => WaitlistSource::Rms,
            'notified_at' => null,
            'notified_by' => null,
            'notified_channel' => null,
            'removed_at' => null,
            'removed_by' => null,
            'removed_reason' => null,
        ];
    }
}
