<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\Documents\DocumentPlan;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Bookings\ReservationFixtures;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

function indexCabin(string $date, string $cabin, string $reference): Booking
{
    $departure = ReservationFixtures::anamaraDeparture($date);

    return Booking::factory()->create([
        'departure_id' => $departure->id,
        'cabin_id' => $departure->yacht->cabins->firstWhere('code', $cabin)?->id,
        'status' => BookingStatus::Confirmed,
        'reference' => $reference,
        'total' => 26600,
    ]);
}

test('the client-documents list agrees with each booking plan', function (): void {
    $first = indexCabin('2028-11-05', 'S1', 'ANK-2026-6501');
    $second = indexCabin('2028-11-12', 'S1', 'ANK-2026-6502');
    $actor = adminUser();

    $response = $this->actingAs($actor)
        ->getJson('/api/rms/documents?from=2028-11-01&to=2028-11-30')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('booking_id')->unique()->sort()->values();
    expect($ids->all())->toBe([$first->id, $second->id]);

    $plan = app(DocumentPlan::class);
    $firstPlan = collect($plan->for($first, $actor))->pluck('kind.value')->all();
    $fromIndex = collect($response->json('data'))
        ->where('booking_id', $first->id)
        ->pluck('kind')
        ->all();

    expect($fromIndex)->toBe($firstPlan);
});

test('the client-documents query count does not grow with extra bookings', function (): void {
    $actor = adminUser();
    indexCabin('2028-11-05', 'S1', 'ANK-2026-6503');

    $this->actingAs($actor)
        ->getJson('/api/rms/documents?from=2028-11-01&to=2028-12-31')
        ->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($actor)
        ->getJson('/api/rms/documents?from=2028-11-01&to=2028-12-31')
        ->assertOk();
    $before = count(DB::getQueryLog());

    indexCabin('2028-11-12', 'S2', 'ANK-2026-6504');
    indexCabin('2028-11-19', 'S3', 'ANK-2026-6505');

    DB::flushQueryLog();
    $this->actingAs($actor)
        ->getJson('/api/rms/documents?from=2028-11-01&to=2028-12-31')
        ->assertOk();
    $after = count(DB::getQueryLog());

    expect($after)->toBe($before);
});
