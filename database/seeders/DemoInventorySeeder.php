<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ReferenceType;
use App\Models\Departure;
use App\Models\Itinerary;
use App\Models\Yacht;
use App\Services\References\ReferenceService;
use App\Support\Departures\SeedMapper as DepartureSeedMapper;
use App\Support\Itineraries\SeedMapper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

final class DemoInventorySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        foreach ($this->itineraryRows() as $row) {
            $attributes = SeedMapper::fromPrototype($row);
            $code = (string) $attributes['code'];
            unset($attributes['code']);

            Itinerary::query()->firstOrCreate(
                ['code' => $code],
                $attributes,
            );
        }

        $yachts = Yacht::query()->get()->keyBy('code');
        $itineraries = Itinerary::query()->get()->keyBy('code');

        foreach ($this->departureRows() as $row) {
            $mapped = DepartureSeedMapper::fromPrototype($row);
            $yacht = $yachts->get($mapped['yacht_code']);
            $itinerary = $itineraries->get($mapped['itinerary_code']);

            if (! $yacht instanceof Yacht || ! $itinerary instanceof Itinerary) {
                throw new RuntimeException(
                    "Demo departure {$mapped['reference']} is missing yacht {$mapped['yacht_code']} or itinerary {$mapped['itinerary_code']}.",
                );
            }

            Departure::query()->firstOrCreate(
                [
                    'yacht_id' => $yacht->id,
                    'date' => $mapped['date'],
                ],
                [
                    'reference' => $mapped['reference'],
                    'itinerary_id' => $itinerary->id,
                    'status' => $mapped['status'],
                    'urgency_threshold' => $mapped['urgency_threshold'],
                    'waitlist_enabled' => $mapped['waitlist_enabled'],
                    'public_note' => $mapped['public_note'],
                    'festive' => $mapped['festive'],
                ],
            );
        }

        DB::transaction(fn () => app(ReferenceService::class)->ensureAtLeast(ReferenceType::Departure, 16));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itineraryRows(): array
    {
        return $this->seedSection('itineraries');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function departureRows(): array
    {
        return $this->seedSection('departures');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function seedSection(string $key): array
    {
        $path = base_path('docs/requirements/examples/seed-data.json');

        try {
            /** @var array<string, list<array<string, mixed>>> $seed */
            $seed = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('seed-data.json is not valid JSON.', 0, $exception);
        }

        return $seed[$key] ?? [];
    }
}
