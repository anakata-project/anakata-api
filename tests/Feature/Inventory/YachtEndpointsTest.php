<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
});

test('a sales exec can list yachts with cabins in sort order', function (): void {
    $sales = salesExecUser();

    $response = $this->actingAs($sales)->getJson('/api/rms/yachts');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(2);
    expect(array_column($data, 'code'))->toBe(['ANAMARA', 'ANATIVA']);
    expect($data[0]['cabins'])->toHaveCount(9);
    expect(array_column($data[0]['cabins'], 'code'))->toBe([
        'S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'S8', 'OWNER',
    ]);
    expect($data[0]['cabins'][8]['label'])->toBe("Owner's Suite");
    expect($data[0]['cabins'][8]['category'])->toBe('OWNER');
});

test('a user without panel.rms cannot list yachts', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/rms/yachts')->assertForbidden();
});
