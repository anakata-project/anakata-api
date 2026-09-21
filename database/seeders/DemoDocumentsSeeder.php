<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Documents\PrepareIssueDocument;
use App\Enums\BookingStatus;
use App\Enums\DocumentKind;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Document;
use App\Models\Payment;
use Illuminate\Database\Seeder;

final class DemoDocumentsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $issuer = app(PrepareIssueDocument::class);
        $bookings = Booking::query()
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::FullyPaid])
            ->get();

        foreach ($bookings as $booking) {
            $this->issueOnce($issuer, $booking, DocumentKind::Invoice);
            $this->issueOnce($issuer, $booking, DocumentKind::Summary);
        }

        $payments = Payment::query()
            ->where('status', PaymentStatus::Settled)
            ->where('amount', '>', 0)
            ->get();

        foreach ($payments as $payment) {
            $issuer->handle(
                $payment->booking,
                DocumentKind::Receipt,
                payment: $payment,
                system: true,
            );
        }
    }

    private function issueOnce(PrepareIssueDocument $issuer, Booking $booking, DocumentKind $kind): void
    {
        $exists = Document::query()
            ->where('booking_id', $booking->id)
            ->where('kind', $kind)
            ->exists();

        if ($exists) {
            return;
        }

        $issuer->handle($booking, $kind, system: true);
    }
}
