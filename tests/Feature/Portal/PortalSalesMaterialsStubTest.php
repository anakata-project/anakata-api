<?php

declare(strict_types=1);

use App\Support\Agencies\PortalPreview;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('sales materials returns an empty list with its note, until task 04', function (): void {
    $user = agencyUser();

    $this->actingAs($user, 'agency')
        ->withHeaders(portalHeaders())
        ->getJson('/api/portal/sales-materials')
        ->assertOk()
        ->assertExactJson([
            'data' => [],
            'meta' => [
                'note' => PortalPreview::MATERIALS_NOTE,
            ],
        ]);
});
