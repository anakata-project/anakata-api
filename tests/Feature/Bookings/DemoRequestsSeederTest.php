<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\HoldRule;
use App\Enums\ReferenceType;
use App\Models\Booking;
use App\Models\WaitlistEntry;
use App\Services\References\ReferenceService;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\DemoBookingsSeeder;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoRequestsSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(ConfigSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(DemoUsersSeeder::class);
    $this->seed(DemoInventorySeeder::class);
    $this->seed(DemoBookingsSeeder::class);
});

test('demo requests seed is idempotent with pinned 2026 references and relative SLAs', function (): void {
    $now = Carbon::parse('2026-09-20 15:00:00', 'UTC');
    Carbon::setTestNow($now);

    try {
        $this->seed(DemoRequestsSeeder::class);
        $this->seed(DemoRequestsSeeder::class);

        $first = Booking::query()->where('request_reference', 'ANK-R-2026-0041')->firstOrFail();
        $second = Booking::query()->where('request_reference', 'ANK-R-2026-0042')->firstOrFail();

        expect(Booking::query()->where('status', BookingStatus::Requested)->count())->toBe(2);
        expect($first->status)->toBe(BookingStatus::Requested);
        expect($first->bookingRequest?->hold_rule)->toBe(HoldRule::LongLead);
        expect($second->bookingRequest?->travel_advisor)->toBeTrue();

        $firstRemaining = (int) $now->diffInMinutes($first->bookingRequest?->sla_due_at, false);
        $secondRemaining = (int) $now->diffInMinutes($second->bookingRequest?->sla_due_at, false);

        expect($firstRemaining)->toBeGreaterThan(18 * 60);
        expect($firstRemaining)->toBeLessThanOrEqual(19 * 60);
        expect($secondRemaining)->toBeLessThan(0);
        expect(abs($secondRemaining))->toBeGreaterThan(25 * 60);
        expect(abs($secondRemaining))->toBeLessThanOrEqual(26 * 60);

        expect(WaitlistEntry::query()->count())->toBe(2);

        $next = DB::transaction(fn (): string => app(ReferenceService::class)->next(ReferenceType::Request, $now));
        expect($next)->toBe('ANK-R-2026-0043');
    } finally {
        Carbon::setTestNow();
    }
});
