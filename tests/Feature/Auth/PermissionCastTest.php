<?php

declare(strict_types=1);

use App\Casts\PermissionCollection;
use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    PermissionCollection::resetLoggedUnknowns();
    Event::fake([MessageLogged::class]);
});

test('unknown permission values are dropped and logged once per request', function (): void {
    $role = Role::factory()->create(['permissions' => []]);

    DB::table('roles')->where('id', $role->id)->update([
        'permissions' => json_encode(['panel.rms', 'gone.away', 'gone.away']),
    ]);

    $first = $role->fresh();
    expect($first?->permissions->map->value->all())->toBe([Permission::PanelRms->value]);

    $second = $role->fresh();
    expect($second?->permissions->map->value->all())->toBe([Permission::PanelRms->value]);

    Event::assertDispatched(MessageLogged::class, function (MessageLogged $event): bool {
        return $event->level === 'warning'
            && $event->message === 'Unknown permission dropped from role'
            && ($event->context['permission'] ?? null) === 'gone.away';
    });

    Event::assertDispatchedTimes(MessageLogged::class, 1);
});

test('saved permissions are unique and sorted', function (): void {
    $role = Role::factory()->create([
        'permissions' => [
            Permission::PanelCrm,
            Permission::PanelRms,
            Permission::PanelRms,
        ],
    ]);

    expect(json_decode((string) $role->getRawOriginal('permissions'), true))->toBe([
        Permission::PanelCrm->value,
        Permission::PanelRms->value,
    ]);
});
