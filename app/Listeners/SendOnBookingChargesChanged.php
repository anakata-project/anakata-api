<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Documents\PrepareIssueDocument;
use App\Actions\Documents\SendDocument;
use App\Enums\BookingStatus;
use App\Enums\DocumentKind;
use App\Events\BookingChargesChanged;
use App\Models\Booking;
use App\Models\Document;
use App\Support\Documents\ChargeSideTotals;
use App\Support\Documents\Snapshots\SnapshotFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class SendOnBookingChargesChanged implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private readonly PrepareIssueDocument $prepare,
        private readonly SendDocument $send,
    ) {}

    public function handle(BookingChargesChanged $event): void
    {
        $booking = Booking::query()->find($event->booking->id);

        if (! $booking instanceof Booking) {
            return;
        }

        if (! $booking->status->isConfirmedOrLater()) {
            return;
        }

        if (in_array($booking->status, [
            BookingStatus::Cancelled,
            BookingStatus::CancelledPostpaid,
            BookingStatus::Released,
        ], true)) {
            return;
        }

        $latest = Document::query()
            ->where('booking_id', $booking->id)
            ->where('kind', DocumentKind::Invoice)
            ->orderByDesc('version')
            ->first();

        if (! $latest instanceof Document) {
            return;
        }

        $fresh = SnapshotFactory::build($booking, DocumentKind::Invoice, null, true);

        if (! ChargeSideTotals::differ($latest->snapshot, $fresh)) {
            return;
        }

        $next = $this->prepare->handle(
            $booking,
            DocumentKind::Invoice,
            reason: $event->reason,
            system: true,
        );
        $this->send->handle($booking, $next, system: true);
    }
}
