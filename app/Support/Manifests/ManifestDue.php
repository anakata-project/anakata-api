<?php

declare(strict_types=1);

namespace App\Support\Manifests;

use App\Models\Departure;
use App\Services\Config\CurrentConfig;
use App\Support\Config\Documents\ManifestsRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class ManifestDue
{
    public function __construct(
        public string $dpng,
        public int $dpngDays,
        public bool $charter,
        public string $captain,
        public int $captainDays,
        public string $chase,
    ) {}

    /**
     * @param  Collection<int, ManifestPassenger>  $passengers
     */
    public static function forDeparture(Departure $departure, Collection $passengers, ?ManifestsRules $rules = null): self
    {
        $rules ??= app(CurrentConfig::class)->businessRules()->manifests;
        $charter = ManifestRoster::isCharter($passengers);
        $dpngDays = $charter ? $rules->dpngCharterDays : $rules->dpngFitDays;
        $departureDate = CarbonImmutable::parse($departure->date->toDateString());

        $dpng = $departureDate->subDays($dpngDays)->toDateString();

        return new self(
            $dpng,
            $dpngDays,
            $charter,
            $departureDate->subDays($rules->captainDays)->toDateString(),
            $rules->captainDays,
            CarbonImmutable::parse($dpng)->subDays($rules->chaseDaysBeforeDue)->toDateString(),
        );
    }
}
