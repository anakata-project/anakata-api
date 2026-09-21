<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BehaviouralEventName;
use App\Models\BehaviouralEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BehaviouralEvent>
 */
class BehaviouralEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'session_id' => str_replace('-', '', (string) Str::uuid()),
            'contact_id' => null,
            'name' => BehaviouralEventName::PageView,
            'params' => ['page_path' => '/itineraries/western-realm'],
            'occurred_at' => now(),
            'received_at' => now(),
        ];
    }
}
