<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Yacht;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Yacht>
 */
class YachtFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('????'));

        return [
            'code' => $code,
            'name' => $code,
        ];
    }
}
