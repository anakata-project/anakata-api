<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Support\Iso;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final class SyncFailures
{
    /**
     * @return list<array{id: string, kind: string, name: string, at: string, detail: string}>
     */
    public static function all(): array
    {
        return self::jobs()
            ->concat(self::deliveries())
            ->sortByDesc('at')
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{id: string, kind: string, name: string, at: string, detail: string}>
     */
    private static function jobs(): Collection
    {
        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->get()
            ->map(function (stdClass $row): array {
                $payload = is_string($row->payload) ? json_decode($row->payload, true) : [];
                $name = is_array($payload) && is_string($payload['displayName'] ?? null)
                    ? $payload['displayName']
                    : (is_array($payload) && is_string($payload['job'] ?? null) ? $payload['job'] : 'job');

                return [
                    'id' => 'job:'.(string) $row->uuid,
                    'kind' => 'job',
                    'name' => $name,
                    'at' => Iso::utc(CarbonImmutable::parse((string) $row->failed_at)),
                    'detail' => self::firstLine(is_string($row->exception) ? $row->exception : ''),
                ];
            });
    }

    /**
     * @return Collection<int, array{id: string, kind: string, name: string, at: string, detail: string}>
     */
    private static function deliveries(): Collection
    {
        $rows = [];

        $deliveries = Delivery::query()
            ->with('booking')
            ->where('status', DeliveryStatus::Failed)
            ->orderByDesc('updated_at')
            ->get();

        foreach ($deliveries as $delivery) {
            $reference = $delivery->booking->displayReference();
            $rows[] = [
                'id' => 'delivery:'.$delivery->id,
                'kind' => 'delivery',
                'name' => $delivery->kind->label().($reference !== null ? ' · '.$reference : ''),
                'at' => Iso::utc($delivery->updated_at),
                'detail' => self::firstLine($delivery->error ?? ''),
            ];
        }

        return collect($rows);
    }

    private static function firstLine(string $text): string
    {
        $line = strtok(str_replace(["\r\n", "\r"], "\n", $text), "\n");

        return is_string($line) ? $line : '';
    }
}
