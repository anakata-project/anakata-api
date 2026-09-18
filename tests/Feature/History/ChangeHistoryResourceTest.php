<?php

declare(strict_types=1);

use App\Http\Resources\Rms\ChangeHistoryResource;
use App\Models\Role;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

test('the resource returns actor, source and an ISO-8601 UTC at ending in Z', function (): void {
    $actor = User::factory()->create(['name' => 'Carolina']);
    $role = Role::factory()->create(['name' => 'Manager']);

    $this->actingAs($actor);
    $this->app->instance('request', Request::create('/api/rms/roles', 'GET'));

    $entry = DB::transaction(fn () => History::record(
        $role,
        'role.updated',
        ['name' => 'Old'],
        ['name' => 'Manager'],
        'Renamed for the matrix',
    ));
    $entry->load('actor');

    $json = (new ChangeHistoryResource($entry))->resolve();

    expect($json['id'])->toBe($entry->id);
    expect($json['event'])->toBe('role.updated');
    expect($json['subject_type'])->toBe('role');
    expect($json['subject_id'])->toBe($role->id);
    expect($json['subject_label'])->toBe('Manager');
    expect($json['actor'])->toBe(['id' => $actor->id, 'name' => 'Carolina']);
    expect($json['actor_label'])->toBe('Carolina');
    expect($json['before'])->toBe(['name' => 'Old']);
    expect($json['after'])->toBe(['name' => 'Manager']);
    expect($json['reason'])->toBe('Renamed for the matrix');
    expect($json['source'])->toBe('rms');
    expect($json['at'])->toEndWith('Z');
    expect($json['at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
    expect($json['at'])->not->toContain('+');
});

test('the resource returns a null actor for System entries', function (): void {
    $role = Role::factory()->create();

    $entry = DB::transaction(fn () => History::record($role, 'role.created'));
    $entry->load('actor');

    $json = (new ChangeHistoryResource($entry))->resolve();

    expect($json['actor'])->toBeNull();
    expect($json['actor_label'])->toBe('System');
    expect($json['source'])->toBe('system');
});
