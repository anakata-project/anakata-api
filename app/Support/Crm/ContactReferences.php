<?php

declare(strict_types=1);

namespace App\Support\Crm;

use Illuminate\Support\Facades\DB;

final class ContactReferences
{
    /**
     * Every table that stores a contact foreign key and must move on merge.
     *
     * @return list<array{table: string, column: string}>
     */
    public static function tables(): array
    {
        return [
            ['table' => 'bookings', 'column' => 'contact_id'],
            ['table' => 'groups', 'column' => 'coordinator_contact_id'],
            ['table' => 'waitlist_entries', 'column' => 'contact_id'],
            ['table' => 'charter_enquiries', 'column' => 'contact_id'],
            ['table' => 'behavioural_events', 'column' => 'contact_id'],
            ['table' => 'contact_consents', 'column' => 'contact_id'],
            ['table' => 'deals', 'column' => 'contact_id'],
            ['table' => 'crm_tasks', 'column' => 'contact_id'],
            ['table' => 'contact_activities', 'column' => 'contact_id'],
        ];
    }

    public static function columnFor(string $table): string
    {
        foreach (self::tables() as $reference) {
            if ($reference['table'] === $table) {
                return $reference['column'];
            }
        }

        throw new \InvalidArgumentException('Unknown contact-bearing table ['.$table.'].');
    }

    /**
     * @return list<array{table: string, id: int}>
     */
    public static function repoint(int $from, int $to): array
    {
        $moved = [];

        foreach (self::tables() as $reference) {
            $ids = DB::table($reference['table'])
                ->where($reference['column'], $from)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            if ($ids === []) {
                continue;
            }

            DB::table($reference['table'])
                ->whereIn('id', $ids)
                ->update([$reference['column'] => $to]);

            foreach ($ids as $id) {
                $moved[] = ['table' => $reference['table'], 'id' => (int) $id];
            }
        }

        return $moved;
    }

    /**
     * @param  list<array{table: string, id: int}>  $rows
     * @return list<array{table: string, id: int}>
     */
    public static function restoreIfStillOn(array $rows, int $survivorId, int $loserId): array
    {
        $skipped = [];

        foreach ($rows as $row) {
            $column = self::columnFor($row['table']);
            $current = DB::table($row['table'])->where('id', $row['id'])->value($column);

            if ((int) $current !== $survivorId) {
                $skipped[] = $row;

                continue;
            }

            DB::table($row['table'])->where('id', $row['id'])->update([$column => $loserId]);
        }

        return $skipped;
    }
}
