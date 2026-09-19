<?php

declare(strict_types=1);

use App\Http\Resources\Rms\ChangeHistoryResource;
use App\Models\Role;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

const UTC_MS_Z = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/';

test('a model toArray datetime uses milliseconds and Z', function (): void {
    $user = User::factory()->create();

    expect($user->toArray()['created_at'])->toMatch(UTC_MS_Z);
});

test('json_encode of now uses milliseconds and Z', function (): void {
    expect(json_decode((string) json_encode(now())))->toMatch(UTC_MS_Z);
});

test('a resource datetime uses milliseconds and Z', function (): void {
    $role = Role::factory()->create();

    $entry = DB::transaction(fn () => History::record($role, 'role.created'));

    $this->app->instance('request', Request::create('/api/rms/roles', 'GET'));

    expect((new ChangeHistoryResource($entry))->resolve()['at'])->toMatch(UTC_MS_Z);
});
