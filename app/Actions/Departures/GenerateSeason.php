<?php

declare(strict_types=1);

namespace App\Actions\Departures;

use App\Actions\Action;
use App\Enums\DepartureStatus;
use App\Enums\SeasonPattern;
use App\Models\Departure;
use App\Models\Itinerary;
use App\Models\Yacht;
use App\Support\Departures\YachtDateConflict;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class GenerateSeason extends Action
{
    /**
     * @param  list<int>  $yachtIds
     * @return array{created: list<string>, skipped: list<array{yacht: string, date: string}>}
     */
    public function handle(
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $yachtIds,
        SeasonPattern $pattern,
        bool $festiveWindow,
        DepartureStatus $status,
    ): array {
        $itineraries = $this->resolveItineraries($pattern, $festiveWindow);
        $yachts = $this->yachtsInOrder($yachtIds);

        return $this->transaction(function () use ($from, $to, $yachts, $itineraries, $pattern, $festiveWindow, $status): array {
            $created = [];
            $skipped = [];
            $week = 0;
            $cursor = $from->dayOfWeek === CarbonInterface::SUNDAY
                ? $from
                : $from->next(CarbonInterface::SUNDAY);

            while ($cursor->lte($to)) {
                foreach ($yachts->values() as $index => $yacht) {
                    $exists = Departure::query()
                        ->where('yacht_id', $yacht->id)
                        ->whereDate('date', $cursor->toDateString())
                        ->exists();

                    if ($exists) {
                        $skipped[] = [
                            'yacht' => $yacht->code,
                            'date' => $cursor->toDateString(),
                        ];

                        continue;
                    }

                    $festive = $festiveWindow && self::inFestiveWindow($cursor);
                    $code = $festive
                        ? 'FEST'
                        : ($pattern === SeasonPattern::Alt
                            ? (($week + $index) % 2 ? 'NORTH' : 'WEST')
                            : $pattern->value);

                    $itinerary = $itineraries->get($code);

                    if (! $itinerary instanceof Itinerary) {
                        throw ValidationException::withMessages([
                            'pattern' => ['Itinerary code '.$code.' does not exist.'],
                        ]);
                    }

                    $departure = YachtDateConflict::guard($yacht, $cursor, function () use ($yacht, $cursor, $itinerary, $status, $festive): Departure {
                        return app(CreateDeparture::class)->handle(
                            [
                                'yacht_id' => $yacht->id,
                                'date' => $cursor->toDateString(),
                                'itinerary_id' => $itinerary->id,
                                'status' => $status,
                                'urgency_threshold' => 3,
                                'waitlist_enabled' => true,
                                'public_note' => null,
                                'festive' => $festive,
                            ],
                            ['action' => 'generate season'],
                        );
                    });

                    $created[] = $departure->reference;
                }

                $cursor = $cursor->addWeek();
                $week++;
            }

            return [
                'created' => $created,
                'skipped' => $skipped,
            ];
        });
    }

    public static function inFestiveWindow(CarbonInterface $date): bool
    {
        $month = (int) $date->month;
        $day = (int) $date->day;

        return ($month === 12 && $day >= 15) || ($month === 1 && $day <= 2);
    }

    /**
     * @return Collection<string, Itinerary>
     */
    private function resolveItineraries(SeasonPattern $pattern, bool $festiveWindow): Collection
    {
        $codes = match ($pattern) {
            SeasonPattern::Alt => ['WEST', 'NORTH'],
            SeasonPattern::West => ['WEST'],
            SeasonPattern::North => ['NORTH'],
        };

        if ($festiveWindow) {
            $codes[] = 'FEST';
        }

        $itineraries = Itinerary::query()
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        foreach ($codes as $code) {
            if (! $itineraries->has($code)) {
                throw ValidationException::withMessages([
                    'pattern' => ['Itinerary code '.$code.' does not exist.'],
                ]);
            }
        }

        return $itineraries;
    }

    /**
     * @param  list<int>  $yachtIds
     * @return Collection<int, Yacht>
     */
    private function yachtsInOrder(array $yachtIds): Collection
    {
        $byId = Yacht::query()->whereIn('id', $yachtIds)->get()->keyBy('id');

        return collect($yachtIds)
            ->map(function (int $id) use ($byId): Yacht {
                $yacht = $byId->get($id);

                if (! $yacht instanceof Yacht) {
                    throw ValidationException::withMessages([
                        'yacht_ids' => ['Yacht '.$id.' does not exist.'],
                    ]);
                }

                return $yacht;
            })
            ->values();
    }
}
