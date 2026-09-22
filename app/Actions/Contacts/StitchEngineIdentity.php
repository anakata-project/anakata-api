<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Actions\Action;
use App\Actions\Crm\RecordContactConsent;
use App\Enums\BehaviouralEventName;
use App\Enums\ConsentCapturePoint;
use App\Enums\ConsentPurpose;
use App\Models\BehaviouralEvent;
use App\Models\Contact;
use App\Services\Config\CurrentConfig;
use App\Support\Crm\AttributionTouch;
use App\Support\History\History;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class StitchEngineIdentity extends Action
{
    /**
     * @param  array<string, mixed>|null  $attribution
     */
    public function handle(Contact $contact, ?string $sessionId, ?array $attribution = null): void
    {
        $this->transaction(function () use ($contact, $sessionId, $attribution): void {
            if (is_string($sessionId) && $sessionId !== '') {
                $this->stitch($contact, $sessionId);
            }

            $this->applyTouches($contact, $attribution);
        });
    }

    private function stitch(Contact $contact, string $sessionId): void
    {
        $backfilled = BehaviouralEvent::query()
            ->where('session_id', $sessionId)
            ->whereNull('contact_id')
            ->update(['contact_id' => $contact->id]);

        $already = BehaviouralEvent::query()
            ->where('session_id', $sessionId)
            ->where('contact_id', $contact->id)
            ->where('name', BehaviouralEventName::IdentityStitched->value)
            ->exists();

        if (! $already || $backfilled > 0) {
            $now = now();

            BehaviouralEvent::query()->create([
                'event_id' => (string) Str::uuid(),
                'session_id' => $sessionId,
                'contact_id' => $contact->id,
                'name' => BehaviouralEventName::IdentityStitched,
                'params' => ['count' => $backfilled],
                'occurred_at' => $now,
                'received_at' => $now,
            ]);

            History::record($contact, 'identity.stitched', after: [
                'count' => $backfilled,
            ], system: true);
        }

        $this->recordAnalyticsConsent($contact, $sessionId);

        if ($contact->engine_identified_at === null) {
            Contact::query()
                ->whereKey($contact->id)
                ->whereNull('engine_identified_at')
                ->update([
                    'engine_identified_at' => now(),
                    'updated_at' => now(),
                ]);

            $contact->refresh();
        }
    }

    private function recordAnalyticsConsent(Contact $contact, string $sessionId): void
    {
        $earliest = BehaviouralEvent::query()
            ->where('session_id', $sessionId)
            ->where('name', '!=', BehaviouralEventName::IdentityStitched->value)
            ->min('occurred_at');

        $capturedAt = is_string($earliest) && $earliest !== ''
            ? Carbon::parse($earliest)
            : now();

        app(RecordContactConsent::class)->handle(
            $contact,
            ConsentPurpose::Analytics,
            granted: true,
            version: app(CurrentConfig::class)->businessRules()->consentVersions->analytics,
            capturePoint: ConsentCapturePoint::EngineBanner,
            capturedAt: $capturedAt,
            sessionId: $sessionId,
        );
    }

    /**
     * @param  array<string, mixed>|null  $attribution
     */
    private function applyTouches(Contact $contact, ?array $attribution): void
    {
        if ($attribution === null) {
            return;
        }

        $first = AttributionTouch::from($attribution['first_touch'] ?? null);
        $last = AttributionTouch::from($attribution['last_touch'] ?? null);
        $changed = [];

        if ($first !== null && $contact->first_touch === null) {
            $changed['first_touch'] = $first;
        }

        if ($last !== null && $last !== $contact->last_touch) {
            $changed['last_touch'] = $last;
        }

        if ($changed === []) {
            return;
        }

        Contact::query()->whereKey($contact->id)->update([
            ...$this->encode($changed),
            'updated_at' => now(),
        ]);

        History::record(
            $contact,
            'contact.updated',
            after: ['fields' => array_keys($changed)],
            system: true,
        );

        $contact->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function encode(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            if (is_array($value)) {
                $attributes[$key] = json_encode($value);
            }
        }

        return $attributes;
    }
}
