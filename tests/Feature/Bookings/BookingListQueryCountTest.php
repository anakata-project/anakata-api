<?php

declare(strict_types=1);

use App\Actions\Bookings\CreateBookingRequest;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Payment;
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
        $extra = app(CreateBookingRequest::class)->handle(
            ReservationFixtures::requestPayload($departure, [
                'cabins' => [['cabin_code' => $cabin, 'adults' => 2, 'children' => 0]],
            ]),
            $actor,
        );
        Guest::factory()->create([
            'booking_id' => $extra->id,
            'first_name' => 'Query',
            'last_name' => 'Count',
        ]);
    }

    DB::flushQueryLog();
    $this->actingAs($actor)
        ->getJson('/api/rms/bookings?from=2027-11-01&to=2027-12-31')
        ->assertOk();
    $after = count(DB::getQueryLog());

    expect($after)->toBe($before);
});

test('the bookings list query count does not grow when the extra bookings have payments', function (): void {
    $actor = managerUser();
    $departure = ReservationFixtures::anamaraDeparture('2028-06-04');

    $first = Booking::factory()->create([
        'departure_id' => $departure->id,
        'cabin_id' => $departure->yacht->cabins->firstWhere('code', 'S1')?->id,
        'owner_id' => $actor->id,
        'reference' => 'ANK-2026-0601',
    ]);
    Payment::factory()->create([
        'booking_id' => $first->id,
        'kind' => PaymentKind::Deposit,
        'status' => PaymentStatus::Settled,
        'amount' => $first->depositAmount(),
        'reference' => 'ANK-2026-0601-D01',
    ]);

    $this->actingAs($actor)
        ->getJson('/api/rms/bookings?from=2028-06-01&to=2028-06-30')
        ->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($actor)
        ->getJson('/api/rms/bookings?from=2028-06-01&to=2028-06-30')
        ->assertOk();
    $before = count(DB::getQueryLog());

    foreach (['S2' => '0602', 'S3' => '0603', 'S4' => '0604'] as $cabin => $suffix) {
        $booking = Booking::factory()->create([
            'departure_id' => $departure->id,
            'cabin_id' => $departure->yacht->cabins->firstWhere('code', $cabin)?->id,
            'owner_id' => $actor->id,
            'reference' => 'ANK-2026-'.$suffix,
        ]);
        Payment::factory()->create([
            'booking_id' => $booking->id,
            'kind' => PaymentKind::Deposit,
            'status' => PaymentStatus::Settled,
            'amount' => $booking->depositAmount(),
            'reference' => 'ANK-2026-'.$suffix.'-D01',
        ]);
    }

    DB::flushQueryLog();
    $this->actingAs($actor)
        ->getJson('/api/rms/bookings?from=2028-06-01&to=2028-06-30')
        ->assertOk()
        ->assertJsonCount(4, 'data');
    $after = count(DB::getQueryLog());

    expect($after)->toBe($before);
});
