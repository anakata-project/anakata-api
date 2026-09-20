<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PreferredChannel;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'country' => null,
            'preferred_channel' => PreferredChannel::Email,
        ];
    }

    public function withoutEmail(): static
    {
        return $this->state(fn (): array => ['email' => null]);
    }
}
