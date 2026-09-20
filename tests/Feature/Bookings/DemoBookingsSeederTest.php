<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\ChannelOfOrigin;
use App\Enums\ClaimKind;
use App\Enums\MainChannel;
use App\Enums\ReferenceType;
use App\Models\Booking;
use App\Models\CabinClaim;
use App\Models\Group;
use App\Services\References\ReferenceService;
use App\Support\Bookings\ChannelSeedMap;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\DemoBookingsSeeder;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(ConfigSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(DemoUsersSeeder::class);
    $this->seed(DemoInventorySeeder::class);
});

test('the seed channel map is the documented four values', function (): void {
    expect(ChannelSeedMap::fromPrototype('WEB_DIRECT'))->toBe([
        'main' => MainChannel::D2C,
        'origin' => ChannelOfOrigin::HotelBookingEngine,
    ]);
    expect(ChannelSeedMap::fromPrototype('INBOUND'))->toBe([
        'main' => MainChannel::D2C,
        'origin' => ChannelOfOrigin::Email,
    ]);
    expect(ChannelSeedMap::fromPrototype('AGENCY'))->toBe([
        'main' => MainChannel::B2BTravelAdvisor,
        'origin' => ChannelOfOrigin::TravelAdvisor,
    ]);
    expect(ChannelSeedMap::fromPrototype('CHARTER_DIRECT'))->toBe([
        'main' => MainChannel::D2C,
        'origin' => ChannelOfOrigin::Email,
    ]);
});

test('demo bookings seed is idempotent and skips requests', function (): void {
    $this->seed(DemoBookingsSeeder::class);
    $this->seed(DemoBookingsSeeder::class);

    expect(Booking::query()->count())->toBe(11);
    expect(Booking::query()->where('status', BookingStatus::Requested)->count())->toBe(0);
    expect(Group::query()->where('reference', 'GRP-007')->exists())->toBeTrue();
    expect(Booking::query()->where('reference', 'ANK-2026-0018')->firstOrFail()->status)->toBe(BookingStatus::Confirmed);
    expect(Booking::query()->where('reference', 'ANK-2026-0012')->firstOrFail()->type)->toBe(BookingType::Charter);
    expect(
        CabinClaim::query()
            ->where('holder_type', 'booking')
            ->where('kind', ClaimKind::Booking)
            ->whereNull('released_at')
            ->count(),
    )->toBe(10 + 9);

    $alvear = Group::query()->where('reference', 'GRP-007')->firstOrFail();
    expect($alvear->bookings)->toHaveCount(3);

    $next = DB::transaction(fn (): string => app(ReferenceService::class)->next(ReferenceType::Booking, now()->setDate(2026, 6, 1)));
    expect($next)->toBe('ANK-2026-0020');

    $nextGroup = DB::transaction(fn (): string => app(ReferenceService::class)->next(ReferenceType::Group));
    expect($nextGroup)->toBe('GRP-008');
});

test('the seeder records calculator totals even when they match the seed', function (): void {
    $this->seed(DemoBookingsSeeder::class);

    expect(DemoBookingsSeeder::$priceDifferences)->toBe([]);
});
