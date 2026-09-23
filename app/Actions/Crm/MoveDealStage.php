<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\DealStage;
use App\Enums\Permission;
use App\Events\DealMarkedLost;
use App\Models\Booking;
use App\Models\Deal;
use App\Models\Group;
use App\Models\User;
use App\Support\Crm\DealStages;
use App\Support\Crm\TaskSweep;
use App\Support\History\History;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class MoveDealStage extends Action
{
    public function handle(Deal $deal, User $actor, DealStage $stage, ?string $reason): Deal
    {
        if (! $stage->isStored()) {
            throw ValidationException::withMessages([
                'stage' => ['That stage follows the booking. Change it in the RMS.'],
            ]);
        }

        if ($deal->isBound()) {
            throw new HttpException(422, 'This deal follows booking '.$this->reference($deal).' — change it in the RMS.');
        }

        if ($deal->owner_id === null) {
            throw new HttpException(422, 'Take this deal before moving it.');
        }

        if ($deal->owner_id !== $actor->id && ! $actor->hasPermission(Permission::RecordsActOnAny)) {
            throw new HttpException(422, 'This deal belongs to another owner.');
        }

        $from = $deal->stage;
        $reopening = $from === DealStage::Lost && $stage->isOpen();

        if (($stage === DealStage::Lost || $reopening) && ($reason === null || trim($reason) === '')) {
            throw ValidationException::withMessages([
                'reason' => ['A reason is required.'],
            ]);
        }

        if ($from === $stage) {
            return $deal;
        }

        return $this->transaction(function () use ($deal, $stage, $reason, $from): Deal {
            $previousEntered = $deal->stage_entered_at->copy();
            $deal->forceFill([
                'stage' => $stage,
                'stage_entered_at' => Carbon::now(),
                'lost_reason' => $stage === DealStage::Lost ? $reason : null,
            ])->save();

            $tasks = app(TaskSweep::class);

            if ($from === DealStage::Quoted && $stage !== DealStage::Quoted) {
                $tasks->onDealLeftQuoted($deal, $previousEntered);
            }

            if ($stage === DealStage::Quoted) {
                $tasks->onDealEnteredQuoted($deal->refresh());
            }

            History::record($deal, 'deal.stage_changed', before: [
                'stage' => $from?->value,
            ], after: [
                'stage' => $stage->value,
                'from' => $from?->value,
                'to' => $stage->value,
            ], reason: $reason);

            if ($stage === DealStage::Lost) {
                DealMarkedLost::dispatch($deal);
            }

            return $deal->refresh();
        });
    }

    private function reference(Deal $deal): string
    {
        if ($deal->group_id !== null) {
            $group = Group::query()->find($deal->group_id);
            $sql = DealStages::bookingColumnSql('COALESCE(bookings.reference, bookings.request_reference)');
            $row = DB::selectOne('SELECT ('.$sql.') AS reference FROM deals WHERE deals.id = ?', [$deal->id]);
            $reference = is_object($row) ? ($row->reference ?? null) : null;

            if (is_string($reference) && $reference !== '') {
                return $reference;
            }

            return $group instanceof Group ? $group->reference : 'the booking';
        }

        $booking = $deal->booking_id !== null ? Booking::query()->find($deal->booking_id) : null;

        if ($booking instanceof Booking) {
            return OpenDealForBooking::reference($booking);
        }

        return 'the booking';
    }
}
