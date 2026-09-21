<?php

declare(strict_types=1);

use App\Enums\ConsentDocument;
use App\Enums\ConsentSource;
use App\Models\Booking;
use App\Models\Consent;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\DemoBookingsSeeder;
use Database\Seeders\DemoConsentsSeeder;
use Database\Seeders\DemoGuestsSeeder;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoRequestsSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(ConfigSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(DemoUsersSeeder::class);
    $this->seed(DemoInventorySeeder::class);
    $this->seed(DemoBookingsSeeder::class);
    $this->seed(DemoRequestsSeeder::class);
    $this->seed(DemoGuestsSeeder::class);
});

test('demo consents match seedOps and a second seed is a no-op', function (): void {
    $this->seed(DemoConsentsSeeder::class);
    $this->seed(DemoConsentsSeeder::class);

    $brandts = Booking::query()->where('reference', 'ANK-2026-0005')->firstOrFail();
    $required = Consent::query()
        ->where('booking_id', $brandts->id)
        ->where('withdrawn', false)
        ->pluck('document')
        ->map(fn ($document): string => $document instanceof ConsentDocument ? $document->value : (string) $document)
        ->all();

    expect($required)->toContain(ConsentDocument::Terms->value);
    expect($required)->toContain(ConsentDocument::Cancellation->value);
    expect($required)->toContain(ConsentDocument::Privacy->value);
    expect($required)->toContain(ConsentDocument::Insurance->value);
    expect($required)->toContain(ConsentDocument::Marketing->value);
    expect(Consent::query()->where('booking_id', $brandts->id)->count())->toBe(5);
    expect(Consent::query()->where('booking_id', $brandts->id)->value('source'))->toBe(ConsentSource::PaymentLink);

    $castellanos = Booking::query()->where('reference', 'ANK-2026-0007')->firstOrFail();
    expect(Consent::query()->where('booking_id', $castellanos->id)->where('document', ConsentDocument::Marketing)->exists())->toBeFalse();
    expect(Consent::query()->where('booking_id', $castellanos->id)->count())->toBe(4);

    $pending = Booking::query()->where('reference', 'ANK-2026-0014')->firstOrFail();
    expect(Consent::query()->where('booking_id', $pending->id)->count())->toBe(0);

    $request = Booking::query()->where('request_reference', 'ANK-R-2026-0041')->firstOrFail();
    expect(Consent::query()->where('booking_id', $request->id)->count())->toBe(0);
});
