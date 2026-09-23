<?php

declare(strict_types=1);

namespace App\Support\Charter;

use App\Actions\Alerts\RaiseAlert;
use App\Actions\Alerts\ResolveAlert;
use App\Actions\Crm\CloseTask;
use App\Actions\Crm\RaiseTask;
use App\Enums\AlertKind;
use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\DocumentKind;
use App\Enums\Permission;
use App\Enums\TaskKind;
use App\Enums\TaskStatus;
use App\Models\Alert;
use App\Models\Booking;
use App\Models\CharterEnquiry;
use App\Models\CrmTask;
use App\Models\Document;
use App\Support\BusinessTime;
use App\Support\Crm\TaskDue;
use App\Support\Payments\Ledger;

final class CharterDepositClock
{
    public function __construct(
        private readonly RaiseTask $raiseTask,
        private readonly CloseTask $closeTask,
        private readonly RaiseAlert $raiseAlert,
        private readonly ResolveAlert $resolveAlert,
    ) {}

    public function sweep(): int
    {
        $raised = 0;
        $today = BusinessTime::now()->setTimezone(BusinessTime::zone())->toDateString();

        Booking::query()
            ->where('type', BookingType::Charter)
            ->whereNotNull('deposit_due_on')
            ->orderBy('id')
            ->each(function (Booking $booking) use ($today, &$raised): void {
                if ($this->settled($booking)) {
                    $this->close($booking, $booking->status === BookingStatus::PendingPayment
                        ? 'the charter deposit was settled'
                        : 'the booking left PENDING_PAYMENT');

                    return;
                }

                if ($booking->deposit_due_on === null || $booking->deposit_due_on->toDateString() >= $today) {
                    return;
                }

                $this->raise($booking);
                $raised++;
            });

        return $raised;
    }

    private function settled(Booking $booking): bool
    {
        if ($booking->status !== BookingStatus::PendingPayment) {
            return true;
        }

        return Ledger::paid($booking) >= $booking->depositAmount();
    }

    private function raise(Booking $booking): void
    {
        $enquiry = CharterEnquiry::query()->where('booking_id', $booking->id)->first();
        $ownerId = null;

        if ($enquiry instanceof CharterEnquiry) {
            $proposal = Document::query()
                ->where('charter_enquiry_id', $enquiry->id)
                ->where('kind', DocumentKind::CharterProposal)
                ->orderByDesc('version')
                ->first();
            $ownerId = $proposal?->issued_by;
        }

        $key = 'charter-deposit:'.$booking->id;
        $due = TaskDue::endOfGalapagosDay($booking->deposit_due_on ?? BusinessTime::now());

        $this->raiseTask->handle(
            TaskKind::CharterDeposit,
            $key,
            'Charter deposit due · '.$booking->reference,
            'The charter deposit is unpaid after '.$booking->deposit_due_on?->toDateString().'.',
            $due,
            $ownerId,
            Permission::BookingsOverdueDecision,
            contactId: $booking->contact_id,
            bookingId: $booking->id,
            charterEnquiryId: $enquiry?->id,
        );

        $this->raiseAlert->handle(
            AlertKind::CharterDepositDue,
            $key,
            'Charter deposit due · '.$booking->reference,
            'A charter deposit is unpaid after its due date.',
            bookingId: $booking->id,
        );
    }

    private function close(Booking $booking, string $fact): void
    {
        $key = 'charter-deposit:'.$booking->id;
        $task = CrmTask::query()->where('idempotency_key', $key)->first();

        if ($task instanceof CrmTask && $task->status === TaskStatus::Open) {
            $this->closeTask->autoClose($task, $fact);
        }

        $alert = Alert::query()->where('base_key', $key)->whereNull('resolved_at')->first();

        if ($alert instanceof Alert) {
            $this->resolveAlert->handle($alert, $fact);
        }
    }
}
