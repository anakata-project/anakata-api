<?php

declare(strict_types=1);

use App\Actions\Bookings\CreateBookingRequest;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Bookings\ReservationFixtures;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
    $this->seed(DemoInventorySeeder::class);
});

test('the bookings list query count does not grow with extra REQUESTED bookings', function (): void {
    $actor = managerUser();
    $departure = ReservationFixtures::anamaraDeparture('2027-11-07');

    app(CreateBookingRequest::class)->handle(
        ReservationFixtures::requestPayload($departure, [
            'cabins' => [['cabin_code' => 'S1', 'adults' => 2, 'children' => 0]],
        ]),
        $actor,
    );

    $this->actingAs($actor)
        ->getJson('/api/rms/bookings?from=2027-11-01&to=2027-12-31')
        ->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($actor)
        ->getJson('/api/rms/bookings?from=2027-11-01&to=2027-12-31')
        ->assertOk();
    $before = count(DB::getQueryLog());

    foreach (['S2', 'S3', 'S4'] as $cabin) {
        app(CreateBookingRequest::class)->handle(
            ReservationFixtures::requestPayload($departure, [
                'cabins' => [['cabin_code' => $cabin, 'adults' => 2, 'children' => 0]],
            ]),
            $actor,
        );
    }

    DB::flushQueryLog();
    $this->actingAs($actor)
        ->getJson('/api/rms/bookings?from=2027-11-01&to=2027-12-31')
        ->assertOk();
    $after = count(DB::getQueryLog());

    expect($after)->toBe($before);
});
