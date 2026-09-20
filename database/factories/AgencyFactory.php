<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AgencyStatus;
use App\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agency>
 */
class AgencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'AG-'.str_pad((string) fake()->unique()->numberBetween(100, 999), 3, '0', STR_PAD_LEFT),
            'name' => fake()->company(),
            'contact' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'country' => 'US',
            'network' => 'Virtuoso',
            'commission_pct' => 10,
            'payment_terms' => '30 days post-cruise · wire',
            'status' => AgencyStatus::Approved,
            'requested_at' => now(),
            'decided_at' => now(),
            'decided_by' => null,
            'decision_reason' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => AgencyStatus::Pending,
            'decided_at' => null,
            'decided_by' => null,
            'decision_reason' => null,
        ]);
    }
}
