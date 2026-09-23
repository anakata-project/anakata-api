<?php

declare(strict_types=1);

namespace App\Support\Automations;

use App\Enums\DeliveryStatus;
use App\Models\AutomationSetting;
use App\Models\Booking;
use App\Models\Delivery;
use App\Support\History\History;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class AutomationGate
{
    public const SKIPPED = 'automation.skipped';

    public function allows(string $key): bool
    {
        $definition = AutomationCatalogue::find($key);

        if (! $definition instanceof AutomationDefinition || ! $definition->built || ! $definition->switchable) {
            return true;
        }

        $setting = AutomationSetting::query()->where('key', $key)->first();

        if (! $setting instanceof AutomationSetting) {
            return true;
        }

        return $setting->enabled;
    }

    public function reason(string $key): string
    {
        $reason = AutomationSetting::query()->where('key', $key)->value('disabled_reason');

        return is_string($reason) && $reason !== '' ? $reason : 'This automation is switched off.';
    }

    public function blockDelivery(Delivery $delivery, string $key, ?Model $subject = null): void
    {
        DB::transaction(function () use ($delivery, $key, $subject): void {
            $fresh = Delivery::query()->find($delivery->id);

            if (! $fresh instanceof Delivery || $fresh->status !== DeliveryStatus::Queued) {
                return;
            }

            $reason = $this->reason($key);
            $fresh->status = DeliveryStatus::Blocked;
            $fresh->blocked_reason = $reason;
            $fresh->save();

            $historySubject = $subject ?? ($fresh->booking instanceof Booking ? $fresh->booking : null);

            if ($historySubject instanceof Model) {
                History::record($historySubject, self::SKIPPED, after: [
                    'key' => $key,
                    'delivery_id' => $fresh->id,
                ], reason: $reason, system: true);
            }
        });
    }

    public function recordSkip(Model $subject, string $key): void
    {
        DB::transaction(function () use ($subject, $key): void {
            History::record($subject, self::SKIPPED, after: [
                'key' => $key,
            ], reason: $this->reason($key), system: true);
        });
    }

    /**
     * A catalogue skip is BLOCKED on purpose. It is not a failed delivery.
     *
     * @param  Builder<Delivery>  $blocked
     */
    public static function excludeSkips(Builder $blocked): void
    {
        $blocked->whereNotExists(function ($history): void {
            $history->selectRaw('1')
                ->from('change_history')
                ->where('change_history.event', self::SKIPPED)
                ->whereRaw(
                    'cast(json_unquote(json_extract(change_history.after, \'$.delivery_id\')) as unsigned) = deliveries.id',
                );
        });
    }
}
