<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ItineraryStatus;
use App\Models\Itinerary;
use App\Support\Itineraries\Defaults;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Itinerary>
 */
class ItineraryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            ...Defaults::attributes(),
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'name' => fake()->words(2, true),
            'status' => ItineraryStatus::Draft,
        ];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $plan
     */
    public function publishable(array $plan = [['Day 1', 'Embark.']]): static
    {
        return $this->state(fn (): array => [
            'name' => 'Western Realm',
            'card_description' => 'Isabela and Fernandina — the wild western edge.',
            'day_plan' => $plan,
            'days' => 8,
            'nights' => 7,
        ]);
    }
}
