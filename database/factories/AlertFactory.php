<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AlertKind;
use App\Enums\AlertSeverity;
use App\Models\Alert;
use App\Support\Alerts\AlertRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kind = AlertKind::OverdueBalance;
        $definition = AlertRegistry::get($kind);
        $key = 'overdue:'.Str::uuid()->toString();

        return [
            'kind' => $kind,
            'severity' => $definition->severity,
            'title' => 'Overdue balance',
            'sentence' => 'A balance is overdue.',
            'booking_id' => null,
            'departure_id' => null,
            'agency_id' => null,
            'payment_id' => null,
            'delivery_id' => null,
            'guest_response_id' => null,
            'crm_task_id' => null,
            'base_key' => $key,
            'idempotency_key' => $key,
            'raised_at' => now(),
            'acknowledged_at' => null,
            'acknowledged_by' => null,
            'resolved_at' => null,
            'resolution' => null,
            'emailed_at' => null,
        ];
    }

    public function critical(): static
    {
        return $this->state(fn (): array => [
            'severity' => AlertSeverity::Critical,
        ]);
    }
}
