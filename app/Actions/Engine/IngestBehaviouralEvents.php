<?php

declare(strict_types=1);

namespace App\Actions\Engine;

use App\Actions\Action;
use App\Enums\BehaviouralEventName;
use App\Models\BehaviouralEvent;
use App\Support\Engine\BehaviouralEventParams;
use Illuminate\Support\Carbon;

final class IngestBehaviouralEvents extends Action
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{accepted: int, duplicate: int}
     */
    public function handle(array $data): array
    {
        $sessionId = (string) $data['session_id'];
        $events = is_array($data['events'] ?? null) ? $data['events'] : [];
        $now = now();
        $floor = $now->copy()->subDay();
        $contactId = $this->currentContactId($sessionId);
        $rows = [];

        foreach ($events as $index => $event) {
            if (! is_array($event)) {
                continue;
            }

            $name = BehaviouralEventName::from((string) $event['name']);
            $params = BehaviouralEventParams::filter(
                $name,
                is_array($event['params'] ?? null) ? $event['params'] : [],
                (int) $index,
            );
            $occurred = $this->clampOccurredAt($event['occurred_at'] ?? null, $now, $floor);

            $rows[] = [
                'event_id' => (string) $event['event_id'],
                'session_id' => $sessionId,
                'contact_id' => $contactId,
                'name' => $name->value,
                'params' => json_encode($params),
                'occurred_at' => $occurred->format('Y-m-d H:i:s'),
                'received_at' => $now->format('Y-m-d H:i:s'),
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
                'created_by' => null,
                'updated_by' => null,
            ];
        }

        $inserted = BehaviouralEvent::query()->insertOrIgnore($rows);

        return [
            'accepted' => $inserted,
            'duplicate' => count($rows) - $inserted,
        ];
    }

    private function currentContactId(string $sessionId): ?int
    {
        $contactId = BehaviouralEvent::query()
            ->where('session_id', $sessionId)
            ->where('name', BehaviouralEventName::IdentityStitched->value)
            ->orderByDesc('id')
            ->value('contact_id');

        return is_numeric($contactId) ? (int) $contactId : null;
    }

    private function clampOccurredAt(mixed $value, Carbon $now, Carbon $floor): Carbon
    {
        $occurred = $value instanceof Carbon
            ? $value
            : Carbon::parse(is_string($value) ? $value : (string) $now);

        $occurred = $occurred->utc();
        $nowUtc = $now->copy()->utc();
        $floorUtc = $floor->copy()->utc();

        if ($occurred->greaterThan($nowUtc)) {
            return $nowUtc;
        }

        if ($occurred->lessThan($floorUtc)) {
            return $floorUtc;
        }

        return $occurred;
    }
}
