<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Itinerary;
use App\Support\Itineraries\SeedMapper;
use Illuminate\Database\Seeder;
use JsonException;
use RuntimeException;

final class DemoInventorySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        foreach ($this->rows() as $row) {
            $attributes = SeedMapper::fromPrototype($row);
            $code = (string) $attributes['code'];
            unset($attributes['code']);

            Itinerary::query()->firstOrCreate(
                ['code' => $code],
                $attributes,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        $path = base_path('docs/requirements/examples/seed-data.json');

        try {
            /** @var array{itineraries?: list<array<string, mixed>>} $seed */
            $seed = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('seed-data.json is not valid JSON.', 0, $exception);
        }

        return $seed['itineraries'] ?? [];
    }
}
