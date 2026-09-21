<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\DocumentKind;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Document;
use App\Models\Payment;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\DemoAgenciesSeeder;
use Database\Seeders\DemoBookingsSeeder;
use Database\Seeders\DemoConsentsSeeder;
use Database\Seeders\DemoDocumentsSeeder;
use Database\Seeders\DemoExtrasSeeder;
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
    $this->seed(DemoAgenciesSeeder::class);
    $this->seed(DemoRequestsSeeder::class);
    $this->seed(DemoGuestsSeeder::class);
    $this->seed(DemoConsentsSeeder::class);
    $this->seed(DemoExtrasSeeder::class);
});

test('demo documents seed issues invoices summaries and receipts without sending', function (): void {
    $this->seed(DemoDocumentsSeeder::class);
    $this->seed(DemoDocumentsSeeder::class);

    $confirmed = Booking::query()
        ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::FullyPaid])
        ->count();

    expect(Document::query()->where('kind', DocumentKind::Invoice)->count())->toBe($confirmed);
    expect(Document::query()->where('kind', DocumentKind::Summary)->count())->toBe($confirmed);

    $settled = Payment::query()->where('status', PaymentStatus::Settled)->where('amount', '>', 0)->count();
    expect(Document::query()->where('kind', DocumentKind::Receipt)->count())->toBe($settled);

    $harrison = Booking::query()->where('reference', 'ANK-2026-0003')->first();
    expect($harrison?->billing_address)->toBe('845 Ocean Drive, Miami, FL 33139, USA');
    expect($harrison?->billing_phone)->toBe('+1 305 555 0198');
});
