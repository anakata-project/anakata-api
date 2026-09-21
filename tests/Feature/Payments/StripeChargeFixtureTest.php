<?php

declare(strict_types=1);

use App\Services\Stripe\FakeStripeGateway;
use Carbon\CarbonImmutable;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('file fixture created timestamps follow a travelled galapagos clock', function (): void {
    $gateway = app(FakeStripeGateway::class);
    $gateway->includeFileFixture = true;

    $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'Pacific/Galapagos'));

    $this->actingAs(adminUser())
        ->getJson('/api/rms/payments/reconciliation?from=2026-10-01&to=2026-10-31')
        ->assertOk()
        ->assertJsonPath('counts.in_gateway_not_rms', 2)
        ->assertJsonFragment(['stripe_id' => 'ch_unmatched']);

    $this->actingAs(adminUser())
        ->getJson('/api/rms/payments/reconciliation?from=2026-09-01&to=2026-09-30')
        ->assertOk()
        ->assertJsonPath('counts.in_gateway_not_rms', 0)
        ->assertJsonPath('counts.matched', 0)
        ->assertJsonPath('counts.to_review', 0);
});

test('file fixture near a month boundary stays in the earlier galapagos month', function (): void {
    $gateway = app(FakeStripeGateway::class);
    $gateway->includeFileFixture = true;

    $this->travelTo(CarbonImmutable::parse('2026-10-01 00:30:00', 'Pacific/Galapagos'));

    $this->actingAs(adminUser())
        ->getJson('/api/rms/payments/reconciliation?from=2026-10-01&to=2026-10-31')
        ->assertOk()
        ->assertJsonPath('counts.gateway', 0);

    $this->actingAs(adminUser())
        ->getJson('/api/rms/payments/reconciliation?from=2026-09-01&to=2026-09-30')
        ->assertOk()
        ->assertJsonPath('counts.in_gateway_not_rms', 2);
});
