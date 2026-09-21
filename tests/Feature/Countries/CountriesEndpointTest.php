<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Countries;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

test('rms users receive the ISO list guest validation uses', function (): void {
    $expected = [];

    foreach (Countries::all() as $code => $name) {
        $expected[] = [
            'code' => $code,
            'name' => $name,
        ];
    }

    $this->actingAs(salesExecUser())
        ->getJson('/api/rms/countries')
        ->assertOk()
        ->assertExactJson($expected);
});

test('the list includes seeded guest countries and no wrapper', function (): void {
    $response = $this->actingAs(managerUser())
        ->getJson('/api/rms/countries')
        ->assertOk();

    expect($response->json())->toBeArray();
    expect($response->json('data'))->toBeNull();
    expect($response->json('0.code'))->toBe('AD');
    expect(collect($response->json())->firstWhere('code', 'EC'))->toMatchArray([
        'code' => 'EC',
        'name' => 'Ecuador',
    ]);
});

test('countries require panel.rms, not bookings.create', function (): void {
    $crmOnly = Role::factory()->create([
        'permissions' => [Permission::PanelCrm],
    ]);
    $crmUser = User::factory()->create(['role_id' => $crmOnly->id]);

    $this->actingAs($crmUser)
        ->getJson('/api/rms/countries')
        ->assertForbidden();
});
