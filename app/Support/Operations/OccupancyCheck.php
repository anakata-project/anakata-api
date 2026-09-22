<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Actions\Alerts\RaiseAlert;
use App\Actions\Alerts\ResolveAlert;
use App\Enums\AlertKind;
use App\Enums\DepartureStatus;
use App\Models\Alert;
use App\Models\Departure;
use App\Services\Config\CurrentConfig;
use App\Services\Inventory\Availability;
use App\Support\Alerts\AlertKeys;
use App\Support\BusinessTime;

final class OccupancyCheck
{
    public function __construct(
        private readonly RaiseAlert $raise,
        private readonly ResolveAlert $resolve,
        private readonly CurrentConfig $config,
        private readonly Availability $availability,
    ) {}

    public function run(): void
    {
        $rules = $this->config->businessRules()->alerts;
        $today = BusinessTime::now()->toDateString();
        $until = BusinessTime::calendarDay($today)->addDays($rules->lowOccupancyDaysBefore)->toDateString();

        $departures = Departure::query()
            ->where('status', DepartureStatus::OnSale)
            ->where('date', '>', $today)
            ->where('date', '<=', $until)
            ->orderBy('id')
            ->get();

        $snapshots = $this->availability->forDepartures($departures);
        $keys = [];

        foreach ($departures as $departure) {
            $counts = $snapshots[$departure->id]->counts;
            $sellable = $counts['sold'] + $counts['held'] + $counts['free'];

            if ($sellable === 0) {
                continue;
            }

            $percent = intdiv($counts['sold'] * 100, $sellable);

            if ($percent >= $rules->lowOccupancyPct) {
                continue;
            }

            $key = AlertKeys::occupancy($departure->id);

            $this->raise->handle(
                AlertKind::LowOccupancy,
                $key,
                'Low occupancy '.$departure->reference,
                $departure->reference.' is '.$percent.'% sold ('.$counts['sold'].' of '.$sellable.' cabins), below the '.$rules->lowOccupancyPct.'% threshold.',
                departureId: $departure->id,
            );
            $keys[] = $key;
        }

        $this->resolveCleared($keys);
    }

    /**
     * @param  list<string>  $keys
     */
    private function resolveCleared(array $keys): void
    {
        $query = Alert::query()
            ->where('kind', AlertKind::LowOccupancy)
            ->unresolved()
            ->orderBy('id');

        if ($keys !== []) {
            $query->whereNotIn('base_key', $keys);
        }

        $query->each(function (Alert $alert): void {
            $this->resolve->handle($alert, 'occupancy is no longer below the threshold, or the departure has sailed');
        });
    }
}
