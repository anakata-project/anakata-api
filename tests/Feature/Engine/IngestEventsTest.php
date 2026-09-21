<?php

declare(strict_types=1);

use App\Actions\Contacts\StitchEngineIdentity;
use App\Enums\BehaviouralEventName;
use App\Models\BehaviouralEvent;
use App\Models\Contact;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

function engineSessionId(): string
{
    return str_replace('-', '', (string) Str::uuid());
}

/**
 * @param  array<string, mixed>  $params
 * @return array<string, mixed>
 */
function engineEvent(string $name, array $params = [], ?string $eventId = null, mixed $occurredAt = null): array
{
    return [
        'event_id' => $eventId ?? (string) Str::uuid(),
        'name' => $name,
        'occurred_at' => $occurredAt ?? now()->toIso8601String(),
        'params' => $params,
    ];
}

test('an unknown event name is refused', function (): void {
    $this->postJson('/api/engine/events', [
        'session_id' => engineSessionId(),
        'events' => [engineEvent('not_a_real_event')],
    ])->assertUnprocessable();
});

test('identity.stitched is refused from the client', function (): void {
    $this->postJson('/api/engine/events', [
        'session_id' => engineSessionId(),
        'events' => [engineEvent(BehaviouralEventName::IdentityStitched->value, ['count' => 1])],
    ])->assertUnprocessable();
});

test('a batch of more than 25 events is refused', function (): void {
    $events = [];

    for ($i = 0; $i < 26; $i++) {
        $events[] = engineEvent(BehaviouralEventName::PageView->value, ['page_path' => '/']);
    }

    $this->postJson('/api/engine/events', [
        'session_id' => engineSessionId(),
        'events' => $events,
    ])->assertUnprocessable();
});

test('a replayed batch is a no-op', function (): void {
    $session = engineSessionId();
    $eventId = (string) Str::uuid();
    $payload = [
        'session_id' => $session,
        'events' => [engineEvent(BehaviouralEventName::PageView->value, ['page_path' => '/'], $eventId)],
    ];

    $this->postJson('/api/engine/events', $payload)
        ->assertOk()
        ->assertJsonPath('accepted', 1)
        ->assertJsonPath('duplicate', 0);

    $this->postJson('/api/engine/events', $payload)
        ->assertOk()
        ->assertJsonPath('accepted', 0)
        ->assertJsonPath('duplicate', 1);

    expect(BehaviouralEvent::query()->count())->toBe(1);
});

test('timestamps are clamped to the last 24 hours and not the future', function (): void {
    $this->postJson('/api/engine/events', [
        'session_id' => engineSessionId(),
        'events' => [
            engineEvent(BehaviouralEventName::PageView->value, ['page_path' => '/old'], occurredAt: now()->subDays(3)->toIso8601String()),
            engineEvent(BehaviouralEventName::PageView->value, ['page_path' => '/future'], occurredAt: now()->addHour()->toIso8601String()),
        ],
    ])->assertOk();

    $old = BehaviouralEvent::query()->where('params->page_path', '/old')->firstOrFail();
    $future = BehaviouralEvent::query()->where('params->page_path', '/future')->firstOrFail();

    expect($old->occurred_at->greaterThanOrEqualTo(now()->subDay()->subSeconds(5)))->toBeTrue();
    expect($future->occurred_at->lessThanOrEqualTo(now()->addSeconds(5)))->toBeTrue();
});

test('events store no ip or user agent', function (): void {
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 test-agent'])
        ->postJson('/api/engine/events', [
            'session_id' => engineSessionId(),
            'events' => [engineEvent(BehaviouralEventName::PageView->value, ['page_path' => '/'])],
        ])
        ->assertOk();

    $row = BehaviouralEvent::query()->firstOrFail();
    $encoded = json_encode($row->getAttributes());

    expect($encoded)->not->toContain('Mozilla/5.0');
    expect($row->getAttributes())->not->toHaveKey('ip');
});

test('a complete-page token is redacted and never stored', function (): void {
    $token = 'abc123secretTokenValue';

    $this->postJson('/api/engine/events', [
        'session_id' => engineSessionId(),
        'events' => [engineEvent(BehaviouralEventName::PageView->value, [
            'page_path' => '/complete/'.$token.'/billing',
        ])],
    ])->assertOk();

    $row = BehaviouralEvent::query()->firstOrFail();

    expect($row->params['page_path'])->toBe('/complete/[token]');
    expect(json_encode($row->getAttributes()))->not->toContain($token);

    $contact = Contact::factory()->create();
    app(StitchEngineIdentity::class)->handle($contact, $row->session_id);

    $response = $this->actingAs(adminUser())
        ->getJson('/api/crm/contacts/'.$contact->id)
        ->assertOk();

    assertNoSensitiveFields($response);
    expect(json_encode($response->json()))->not->toContain($token);
});

test('events are rate limited per session', function (): void {
    $session = engineSessionId();

    for ($i = 0; $i < 20; $i++) {
        $this->postJson('/api/engine/events', [
            'session_id' => $session,
            'events' => [engineEvent(BehaviouralEventName::PageView->value, ['page_path' => '/'])],
        ])->assertOk();
    }

    $this->postJson('/api/engine/events', [
        'session_id' => $session,
        'events' => [engineEvent(BehaviouralEventName::PageView->value, ['page_path' => '/'])],
    ])->assertStatus(429);
});
