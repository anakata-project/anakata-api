<?php

declare(strict_types=1);

use App\Actions\Bookings\TransitionBooking;
use App\Actions\Extras\AddBookingExtra;
use App\Actions\Extras\RemoveBookingExtra;
use App\Actions\Payments\RecordPayment;
use App\Enums\BookingStatus;
use App\Enums\DocumentKind;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Document;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
    Mail::fake();
});

afterEach(function (): void {
    Storage::disk('documents')->deleteDirectory('/');
});

function confirmedWithInvoice(): Booking
{
    $booking = pendingCabin(['reference' => 'ANK-2026-6201']);
    app(TransitionBooking::class)->handle($booking, [
        'to' => BookingStatus::Confirmed,
        'reason' => 'Manual confirm',
    ], adminUser());

    return $booking->fresh() ?? $booking;
}

test('adding an extra to a confirmed booking issues invoice v2 with the reason and sends it', function (): void {
    $booking = confirmedWithInvoice();

    app(AddBookingExtra::class)->handle($booking, ['code' => 'FLT', 'qty' => 2], adminUser());

    $invoice = Document::query()
        ->where('booking_id', $booking->id)
        ->where('kind', DocumentKind::Invoice)
        ->orderByDesc('version')
        ->first();

    expect($invoice?->version)->toBe(2);
    expect($invoice?->reason)->toBe('Extra added — Domestic flights GYE/UIO ↔ SCY (round-trip) × 2');
    expect(Document::query()->where('booking_id', $booking->id)->where('kind', DocumentKind::Invoice)->count())->toBe(2);
});

test('adding and removing the same extra in one request issues nothing new', function (): void {
    $booking = confirmedWithInvoice();

    DB::transaction(function () use ($booking): void {
        $extra = app(AddBookingExtra::class)->handle($booking, ['code' => 'FLT', 'qty' => 2], adminUser());
        app(RemoveBookingExtra::class)->handle($extra, adminUser());
    });

    expect(Document::query()->where('booking_id', $booking->id)->where('kind', DocumentKind::Invoice)->count())->toBe(1);
    expect(Document::query()->where('booking_id', $booking->id)->where('kind', DocumentKind::Invoice)->value('version'))->toBe(1);
});

test('FULLY_PAID then extra then paying it sends final invoice v2 once', function (): void {
    $booking = pendingCabin(['reference' => 'ANK-2026-6202']);
    app(RecordPayment::class)->handle($booking, [
        'kind' => PaymentKind::Deposit,
        'method' => PaymentMethod::CardStripe,
        'amount' => 2660,
    ], adminUser());
    $booking = $booking->fresh() ?? $booking;
    app(RecordPayment::class)->handle($booking, [
        'kind' => PaymentKind::Balance,
        'method' => PaymentMethod::CardStripe,
        'amount' => 23940,
    ], adminUser());

    $booking = $booking->fresh() ?? $booking;
    expect($booking->status)->toBe(BookingStatus::FullyPaid);
    expect(Document::query()->where('booking_id', $booking->id)->where('kind', DocumentKind::FinalInvoice)->count())->toBe(1);

    app(AddBookingExtra::class)->handle($booking, ['code' => 'SPA', 'qty' => 1, 'rate_usd' => 100], adminUser());

    $booking = $booking->fresh() ?? $booking;
    app(RecordPayment::class)->handle($booking, [
        'kind' => PaymentKind::Extras,
        'method' => PaymentMethod::CardStripe,
        'amount' => 100,
    ], adminUser());

    $finals = Document::query()
        ->where('booking_id', $booking->id)
        ->where('kind', DocumentKind::FinalInvoice)
        ->orderBy('version')
        ->get();

    expect($finals)->toHaveCount(2);
    expect($finals->last()?->version)->toBe(2);
    expect($finals->last()?->reason)->toBe('Balance paid after charges changed');
});
