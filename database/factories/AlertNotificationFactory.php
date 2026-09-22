<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AlertNotificationStatus;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertNotification>
 */
class AlertNotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alert_id' => Alert::factory(),
            'user_id' => User::factory(),
            'status' => AlertNotificationStatus::Sent,
            'error' => null,
            'sent_at' => now(),
            'attempts' => 1,
        ];
    }
}
