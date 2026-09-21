<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

test('record throws when called outside an action transaction', function (): void {
    $role = Role::factory()->create();

    History::record($role, 'role.created');
})->throws(RuntimeException::class, 'History must be written inside a transaction.');

test('record works inside DB::transaction', function (): void {
    $role = Role::factory()->create();

    $entry = DB::transaction(fn () => History::record($role, 'role.created'));

    expect($entry->exists)->toBeTrue();
    expect(DB::table('change_history')->where('id', $entry->id)->exists())->toBeTrue();
});

test('an authenticated user is stored as the actor', function (): void {
    $actor = User::factory()->create(['name' => 'Carolina']);
    $role = Role::factory()->create();

    $this->actingAs($actor);

    $entry = DB::transaction(fn () => History::record($role, 'role.updated'));

    expect($entry->actor_id)->toBe($actor->id);
    expect($entry->actor_label)->toBe('Carolina');
});

test('no authenticated user is stored as System', function (): void {
    $role = Role::factory()->create();

    $entry = DB::transaction(fn () => History::record($role, 'role.updated'));

    expect($entry->actor_id)->toBeNull();
    expect($entry->actor_label)->toBe('System');
});

test('system: true is stored as System even when a user is authenticated', function (): void {
    $actor = User::factory()->create(['name' => 'Carolina']);
    $role = Role::factory()->create();

    $this->actingAs($actor);

    $entry = DB::transaction(fn () => History::record($role, 'role.updated', system: true));

    expect($entry->actor_id)->toBeNull();
    expect($entry->actor_label)->toBe('System');
});

test('an explicit actor overrides the authenticated user', function (): void {
    $sessionUser = User::factory()->create(['name' => 'Carolina']);
    $actor = User::factory()->create(['name' => 'Mateo']);
    $role = Role::factory()->create();

    $this->actingAs($sessionUser);

    $entry = DB::transaction(fn () => History::record($role, 'role.updated', actor: $actor));

    expect($entry->actor_id)->toBe($actor->id);
    expect($entry->actor_label)->toBe('Mateo');
});

test('diff after save puts the old value in before', function (): void {
    $role = Role::factory()->create(['name' => 'Before']);

    $role->name = 'After';
    $role->save();

    [$before, $after] = History::diff($role);

    expect($before)->toHaveKey('name', 'Before');
    expect($after)->toHaveKey('name', 'After');
    expect($before)->not->toHaveKey('updated_at');
    expect($after)->not->toHaveKey('updated_at');
    expect($before)->not->toHaveKey('updated_by');
    expect($after)->not->toHaveKey('updated_by');
});

test('redaction replaces sensitive values at the top level and keeps the key', function (): void {
    $role = Role::factory()->create();

    $entry = DB::transaction(fn () => History::record(
        $role,
        'guest.updated',
        ['passport_no' => 'X123', 'name' => 'Ada'],
        ['passport_no' => 'Y456', 'name' => 'Ada'],
    ));

    expect($entry->before)->toBe([
        'passport_no' => '[redacted]',
        'name' => 'Ada',
    ]);
    expect($entry->after)->toBe([
        'passport_no' => '[redacted]',
        'name' => 'Ada',
    ]);
});

test('redaction replaces nested sensitive values and keeps the key', function (): void {
    $role = Role::factory()->create();

    $entry = DB::transaction(fn () => History::record(
        $role,
        'guest.updated',
        ['guest' => ['passport_no' => 'X123', 'email' => 'ada@example.com']],
        ['guest' => ['passport_no' => 'Y456', 'email' => 'ada@example.com']],
    ));

    expect($entry->before)->toBe([
        'guest' => [
            'passport_no' => '[redacted]',
            'email' => 'ada@example.com',
        ],
    ]);
    expect($entry->after)->toBe([
        'guest' => [
            'passport_no' => '[redacted]',
            'email' => 'ada@example.com',
        ],
    ]);
});

test('redaction replaces sensitive values in a list of guests', function (): void {
    $role = Role::factory()->create();

    $entry = DB::transaction(fn () => History::record(
        $role,
        'guest.updated',
        ['guests' => [
            ['passport_no' => 'OLD-PP-111', 'medical_note' => 'old-med-note', 'email' => 'ada@example.com'],
            ['passport_no' => 'OLD-PP-222', 'name' => 'Julia'],
        ]],
        ['guests' => [
            ['passport_no' => 'NEW-PP-111', 'medical_note' => 'new-med-note', 'email' => 'ada@example.com'],
            ['passport_no' => 'NEW-PP-222', 'name' => 'Julia'],
        ]],
    ));

    expect($entry->before)->toBe([
        'guests' => [
            ['passport_no' => '[redacted]', 'medical_note' => '[redacted]', 'email' => 'ada@example.com'],
            ['passport_no' => '[redacted]', 'name' => 'Julia'],
        ],
    ]);
    expect($entry->after)->toBe([
        'guests' => [
            ['passport_no' => '[redacted]', 'medical_note' => '[redacted]', 'email' => 'ada@example.com'],
            ['passport_no' => '[redacted]', 'name' => 'Julia'],
        ],
    ]);
});

test('a history entry may name passport number and never stores the values', function (): void {
    $role = Role::factory()->create(['name' => 'Julia Brandt']);

    $entry = DB::transaction(fn () => History::record(
        $role,
        'guest.updated',
        ['passport_no' => 'OLD-PP-JULIA-111', 'medical_note' => 'old-julia-med'],
        ['passport_no' => 'NEW-PP-JULIA-999', 'medical_note' => 'new-julia-med'],
        extraContext: ['passport_no' => 'CTX-PP-SHOULD-REDACT'],
    ));

    expect($entry->before)->toHaveKey('passport_no', '[redacted]');
    expect($entry->after)->toHaveKey('passport_no', '[redacted]');
    expect($entry->context['passport_no'] ?? null)->toBe('[redacted]');

    $row = json_encode(DB::table('change_history')->where('id', $entry->id)->first());

    expect($row)->toBeString();
    expect($row)->toContain('passport_no');
    expect($row)->not->toContain('OLD-PP-JULIA-111');
    expect($row)->not->toContain('NEW-PP-JULIA-999');
    expect($row)->not->toContain('old-julia-med');
    expect($row)->not->toContain('new-julia-med');
    expect($row)->not->toContain('CTX-PP-SHOULD-REDACT');
});

test('source is rms crm engine auth or system from the request path', function (string $path, string $source): void {
    $role = Role::factory()->create();

    $this->app->instance('request', Request::create($path, 'GET'));

    $entry = DB::transaction(fn () => History::record($role, 'role.updated'));

    expect($entry->context['source'])->toBe($source);
})->with([
    ['/api/rms/roles', 'rms'],
    ['/api/crm/contacts', 'crm'],
    ['/api/engine/departures', 'engine'],
    ['/api/auth/reset-password', 'auth'],
    ['/api/health', 'system'],
    ['/horizon', 'system'],
]);

test('request id comes from the X-Request-Id header', function (): void {
    $role = Role::factory()->create();

    $this->app->instance('request', Request::create(
        '/api/rms/roles',
        'GET',
        server: ['HTTP_X_REQUEST_ID' => 'req-sprint-1'],
    ));

    $entry = DB::transaction(fn () => History::record($role, 'role.updated'));

    expect($entry->context['request_id'])->toBe('req-sprint-1');
    expect($entry->context['source'])->toBe('rms');
});
