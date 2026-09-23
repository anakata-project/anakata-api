<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Actions\Action;
use App\Enums\ReportCadence;
use App\Models\ReportSubscription;
use App\Models\User;
use App\Support\History\History;

final class UpdateReportSubscription extends Action
{
    /**
     * @param  array{active?: bool, send_at?: string, weekday?: int|null, day_of_month?: int|null}  $changes
     */
    public function handle(ReportSubscription $subscription, array $changes, User $actor): ReportSubscription
    {
        return $this->transaction(function () use ($subscription, $changes, $actor): ReportSubscription {
            $before = [
                'active' => $subscription->active,
                'send_at' => $subscription->clock(),
                'weekday' => $subscription->weekday,
                'day_of_month' => $subscription->day_of_month,
            ];

            if (array_key_exists('send_at', $changes)) {
                $changes['send_at'] = strlen($changes['send_at']) === 5 ? $changes['send_at'].':00' : $changes['send_at'];
            }

            $subscription->fill($changes);

            if ($subscription->cadence === ReportCadence::Weekly && $subscription->weekday === null) {
                abort(422, 'A weekly subscription needs a weekday.');
            }

            if (in_array($subscription->cadence, [ReportCadence::Monthly, ReportCadence::Quarterly], true)
                && $subscription->day_of_month === null) {
                abort(422, 'This subscription needs a day of the month.');
            }

            if (! $subscription->isDirty()) {
                return $subscription;
            }

            $subscription->save();

            History::record($subscription, 'report_subscription.updated', before: $before, after: [
                'active' => $subscription->active,
                'send_at' => $subscription->clock(),
                'weekday' => $subscription->weekday,
                'day_of_month' => $subscription->day_of_month,
            ], actor: $actor);

            return $subscription->refresh();
        });
    }
}
