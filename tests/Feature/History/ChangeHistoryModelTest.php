<?php

declare(strict_types=1);

use App\Models\ChangeHistory;
use App\Models\Role;
use App\Support\History\History;
use Illuminate\Support\Facades\DB;
use LogicException;

test('history can be inserted through History::record', function (): void {
    $role = Role::factory()->create(['name' => 'Reservations']);

    $entry = DB::transaction(fn () => History::record($role, 'role.created'));

    expect($entry)->toBeInstanceOf(ChangeHistory::class);
    expect($entry->exists)->toBeTrue();
    expect($entry->subject_type)->toBe('role');
    expect($entry->subject_id)->toBe($role->id);
    expect($entry->subject_label)->toBe('Reservations');
    expect($entry->event)->toBe('role.created');
});

test('saving an existing change history row throws', function (): void {
    $role = Role::factory()->create();
    $entry = DB::transaction(fn () => History::record($role, 'role.created'));

    $entry->event = 'role.tampered';
    $entry->save();
})->throws(LogicException::class, 'Change history entries cannot be updated.');

test('updating a change history row throws', function (): void {
    $role = Role::factory()->create();
    $entry = DB::transaction(fn () => History::record($role, 'role.created'));

    $entry->update(['event' => 'role.tampered']);
})->throws(LogicException::class, 'Change history entries cannot be updated.');

test('deleting a change history row throws', function (): void {
    $role = Role::factory()->create();
    $entry = DB::transaction(fn () => History::record($role, 'role.created'));

    $entry->delete();
})->throws(LogicException::class, 'Change history entries cannot be deleted.');
