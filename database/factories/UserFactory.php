<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function withRole(SystemRole $role): static
    {
        return $this->state(function () use ($role): array {
            $model = Role::query()->firstOrCreate(
                ['slug' => $role->value],
                [
                    'name' => $role->label(),
                    'description' => null,
                    'permissions' => $role->defaultPermissions(),
                    'is_system' => true,
                ],
            );

            return ['role_id' => $model->id];
        });
    }
}
