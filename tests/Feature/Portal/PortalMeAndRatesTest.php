<?php

declare(strict_types=1);

use App\Enums\AgencyStatus;
use App\Services\Config\CurrentConfig;
use App\Support\Agencies\PortalPreview;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('me shows the agency commercial profile and the user, with materials not yet available', function (): void {
    $agency = approvedAgency([
        'name' => 'Blue Latitude',
        'commission_pct' => 10,
        'payment_terms' => '30 days post-cruise · wire',
    ]);
    $user = agencyUser(['name' => 'Ana Agent', 'email' => 'ana@agency.test'], $agency);

    $this->actingAs($user, 'agency')
        ->withHeaders(portalHeaders())
        ->getJson('/api/portal/me')
        ->assertOk()
        ->assertJsonPath('agency.name', 'Blue Latitude')
        ->assertJsonPath('agency.reference', $agency->reference)
        ->assertJsonPath('agency.commission_pct', 10)
        ->assertJsonPath('agency.payment_terms', '30 days post-cruise · wire')
        ->assertJsonPath('agency.status', AgencyStatus::Approved->value)
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.name', 'Ana Agent')
        ->assertJsonPath('user.email', 'ana@agency.test')
        ->assertJsonPath('materials_exist', false);
});

test('rates returns only net rates, matching PortalPreview::for', function (): void {
    $agency = approvedAgency(['commission_pct' => 10]);
    $user = agencyUser([], $agency);
    $rates = app(CurrentConfig::class)->rates();

    $response = $this->actingAs($user, 'agency')
        ->withHeaders(portalHeaders())
        ->getJson('/api/portal/rates')
        ->assertOk();

    expect($response->json('data'))->toBe(PortalPreview::for($agency, $rates)['net_rates']);

    $encoded = json_encode($response->json());
    foreach ($rates->years as $year) {
        expect($encoded)->not->toContain('"suite_pp":'.$year->suitePp);
        expect($encoded)->not->toContain('"owner_pp":'.$year->ownerPp);
        expect($encoded)->not->toContain('"charter_week":'.$year->charterWeek);
    }
});
