<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AgencyUserStatus;
use App\Models\Agency;
use App\Models\AgencyUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<AgencyUser>
 */
class AgencyUserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'status' => AgencyUserStatus::InviteOnPortalLaunch,
            'password' => null,
            'accepted_at' => null,
            'last_login_at' => null,
            'invite_token_hash' => null,
            'invite_sent_at' => null,
            'invite_expires_at' => null,
            'invited_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => AgencyUserStatus::Active,
            'password' => Hash::make('password'),
            'accepted_at' => now(),
        ]);
    }

    public function inviteOnApproval(): static
    {
        return $this->state(fn (): array => [
            'status' => AgencyUserStatus::InviteOnApproval,
        ]);
    }

    public function inviteOnPortalLaunch(): static
    {
        return $this->state(fn (): array => [
            'status' => AgencyUserStatus::InviteOnPortalLaunch,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'status' => AgencyUserStatus::Disabled,
            'password' => Hash::make('password'),
            'accepted_at' => now(),
        ]);
    }

    /**
     * @return array{0: static, 1: string}
     */
    public function withPendingInvite(): array
    {
        $token = 'test-invite-token';

        return [
            $this->state(fn (): array => [
                'status' => AgencyUserStatus::InviteOnPortalLaunch,
                'invite_token_hash' => hash('sha256', $token),
                'invite_sent_at' => now(),
                'invite_expires_at' => now()->addDays(14),
            ]),
            $token,
        ];
    }
}
