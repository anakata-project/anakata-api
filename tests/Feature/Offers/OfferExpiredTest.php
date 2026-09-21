<?php

declare(strict_types=1);

use App\Enums\OfferStatus;
use App\Models\Offer;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesSeeder;
use Tests\Support\Offers\OfferFixtures;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    OfferFixtures::west();
});

test('derived expired uses the later window end on the galapagos date', function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-12-01 12:00:00', 'Pacific/Galapagos'));

    $offer = Offer::factory()->live()->create([
        'booking_to' => '2027-06-01',
        'travel_to' => '2027-12-01',
        'itinerary_codes' => ['WEST'],
    ]);

    expect($offer->derivedStatus())->toBe(OfferStatus::Live->value);

    $this->travelTo(CarbonImmutable::parse('2027-12-02 00:30:00', 'Pacific/Galapagos'));

    expect($offer->fresh()?->derivedStatus())->toBe(OfferStatus::DerivedExpired);
});

test('the index status filter includes derived expired', function (): void {
    $this->travelTo(CarbonImmutable::parse('2028-01-02 12:00:00', 'Pacific/Galapagos'));

    Offer::factory()->live()->create([
        'code' => 'GONE',
        'travel_to' => '2027-12-31',
        'itinerary_codes' => ['WEST'],
    ]);
    Offer::factory()->live()->create([
        'code' => 'OPEN',
        'travel_to' => '2028-06-01',
        'itinerary_codes' => ['WEST'],
    ]);

    $expired = $this->actingAs(managerUser())
        ->getJson('/api/rms/offers?status=EXPIRED')
        ->assertOk()
        ->json('data');

    expect(collect($expired)->pluck('code')->all())->toBe(['GONE']);

    $live = $this->actingAs(managerUser())
        ->getJson('/api/rms/offers?status=LIVE')
        ->assertOk()
        ->json('data');

    expect(collect($live)->pluck('code')->all())->toBe(['OPEN']);
});
