<?php

declare(strict_types=1);

use App\Models\Role;
use App\Support\History\History;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('a raw update on change_history fails', function (): void {
    $role = Role::factory()->create();
    $entry = DB::transaction(fn () => History::record($role, 'role.created'));

    expect(fn () => DB::table('change_history')->where('id', $entry->id)->update(['event' => 'role.tampered']))
        ->toThrow(QueryException::class);
});

test('a raw delete on change_history fails', function (): void {
    $role = Role::factory()->create();
    $entry = DB::transaction(fn () => History::record($role, 'role.created'));

    expect(fn () => DB::table('change_history')->where('id', $entry->id)->delete())
        ->toThrow(QueryException::class);
});
