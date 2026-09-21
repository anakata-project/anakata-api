<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Documents\PrepareIssueDocument;
use App\Actions\Documents\SendDocument;
use App\Enums\BookingStatus;
use App\Enums\DocumentKind;
use App\Events\PaymentSettled;
use App\Models\Booking;
use App\Models\Document;
use App\Support\Documents\ChargeSideTotals;
use App\Support\Documents\Snapshots\SnapshotFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class SendOnPaymentSettled implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private readonly PrepareIssueDocument $prepare,
        private readonly SendDocument $send,
    ) {}

    public function handle(PaymentSettled $event): void
    {
        $booking = Booking::query()->find($event->booking->id);

        if (! $booking instanceof Booking) {
            return;
        }

        if ($event->payment->amount > 0) {
            $receipt = $this->prepare->handle(
                $booking,
                DocumentKind::Receipt,
                payment: $event->payment,
                system: true,
            );
            $this->send->handle($booking, $receipt, system: true);
        }

        $booking = $booking->fresh() ?? $booking;

        if ($booking->status !== BookingStatus::FullyPaid || $booking->balanceFresh() > 0) {
            return;
        }

        $latest = Document::query()
            ->where('booking_id', $booking->id)
            ->where('kind', DocumentKind::FinalInvoice)
            ->orderByDesc('version')
            ->first();

        if (! $latest instanceof Document) {
            return;
        }

        $fresh = SnapshotFactory::build($booking, DocumentKind::FinalInvoice, null, true);

        if (! ChargeSideTotals::differ($latest->snapshot, $fresh)) {
            return;
        }

        $next = $this->prepare->handle(
            $booking,
            DocumentKind::FinalInvoice,
            reason: 'Balance paid after charges changed',
            system: true,
        );
        $this->send->handle($booking, $next, system: true);
    }
}
