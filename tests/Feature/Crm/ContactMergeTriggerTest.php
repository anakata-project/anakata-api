<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\ContactMerge;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('the merge trigger allows erase and refuses every other mutation', function (): void {
    $survivor = Contact::factory()->create();
    $loser = Contact::factory()->create();

    $mergeId = $this->actingAs(managerUser())
        ->postJson('/api/crm/contacts/'.$survivor->id.'/merge', [
            'contact_id' => $loser->id,
            'reason' => 'Trigger',
        ])
        ->json('merge.id');

    expect(fn () => DB::table('contact_merges')->where('id', $mergeId)->update(['reason' => 'changed']))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('contact_merges')->where('id', $mergeId)->update(['erased_at' => now()]))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('contact_merges')->where('id', $mergeId)->update([
        'merged_identifiers' => null,
        'loser_fields' => null,
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('contact_merges')->where('id', $mergeId)->delete())
        ->toThrow(QueryException::class);

    DB::table('contact_merges')->where('id', $mergeId)->update([
        'erased_at' => now(),
        'merged_identifiers' => null,
        'loser_fields' => null,
    ]);

    $erased = ContactMerge::query()->findOrFail($mergeId);
    expect($erased->erased_at)->not->toBeNull();
    expect($erased->merged_identifiers)->toBeNull();
    expect($erased->loser_fields)->toBeNull();

    expect(fn () => DB::table('contact_merges')->where('id', $mergeId)->update([
        'erased_at' => now()->addMinute(),
        'merged_identifiers' => null,
        'loser_fields' => null,
    ]))->toThrow(QueryException::class);
});
