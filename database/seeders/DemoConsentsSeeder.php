<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Consents\RecordConsent;
use App\Enums\ConsentDocument;
use App\Enums\ConsentSource;
use App\Models\Booking;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

final class DemoConsentsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $action = app(RecordConsent::class);
        $acceptedAt = CarbonImmutable::create(2026, 7, 2, 14, 5, 0, BusinessTime::zone());

        foreach (Booking::query()->withTrashed()->orderBy('id')->get() as $booking) {
            if (! $booking->status->isConfirmedOrLater()) {
                continue;
            }

            $ip = sprintf('73.%d.41.%d', ($booking->id + 12) % 256, $booking->total % 200);

            foreach (ConsentDocument::cases() as $document) {
                if ($document === ConsentDocument::Marketing && $booking->reference === 'ANK-2026-0007') {
                    continue;
                }

                $action->handle(
                    $booking,
                    $document,
                    ConsentSource::PaymentLink,
                    ip: $ip,
                    acceptedAt: $acceptedAt,
                );
            }
        }
    }
}
