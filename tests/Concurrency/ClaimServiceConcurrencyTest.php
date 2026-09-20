<?php

declare(strict_types=1);

use App\Enums\ClaimKind;
use App\Enums\HoldType;
use App\Enums\ItineraryStatus;
use App\Exceptions\CabinUnavailableException;
use App\Models\Cabin;
use App\Models\CabinClaim;
use App\Models\Departure;
use App\Models\Itinerary;
use App\Models\Yacht;
use App\Services\Inventory\ClaimService;
use Database\Seeders\InventorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Inventory\ClaimHolder;

beforeEach(function (): void {
    $this->seed(InventorySeeder::class);

    config(['database.connections.mysql_lock' => config('database.connections.mysql')]);
    DB::purge('mysql_lock');
    DB::connection('mysql_lock')->statement('SET SESSION innodb_lock_wait_timeout = 1');
});

/**
 * @template T
 *
 * @param  callable(): T  $callback
 * @return T
 */
function onClaimConnection(string $name, callable $callback): mixed
{
    $previous = DB::getDefaultConnection();
    DB::setDefaultConnection($name);

    try {
        return $callback();
    } finally {
        DB::setDefaultConnection($previous);
    }
}

function claimMysqlError(QueryException $e): int
{
    return (int) ($e->errorInfo[1] ?? 0);
}

/**
 * @return array{departure: Departure, cabin: Cabin}
 */
function concurrencyCabin(): array
{
    $yacht = Yacht::query()->where('code', 'ANAMARA')->firstOrFail();
    $departure = Departure::factory()->create([
        'yacht_id' => $yacht->id,
        'itinerary_id' => Itinerary::factory()->create(['status' => ItineraryStatus::Published])->id,
        'date' => '2028-04-02',
    ]);

    return [
        'departure' => $departure,
        'cabin' => $yacht->cabins()->where('code', 'S1')->firstOrFail(),
    ];
}

test('two claimers of the same free cabin never deadlock or double-occupy', function (): void {
    ['departure' => $departure, 'cabin' => $cabin] = concurrencyCabin();
    $first = ClaimHolder::query()->create(['reference' => 'C1', 'name' => 'One']);
    $second = ClaimHolder::query()->create(['reference' => 'C2', 'name' => 'Two']);
    $observed = 'none';

    onClaimConnection('mysql', function () use ($departure, $cabin, $first): void {
        DB::beginTransaction();
        app(ClaimService::class)->claim($departure, collect([$cabin]), $first, ClaimKind::Block);
    });

    onClaimConnection('mysql_lock', function () use ($departure, $cabin, $second, &$observed): void {
        DB::beginTransaction();

        try {
            app(ClaimService::class)->claim($departure, collect([$cabin]), $second, ClaimKind::Block);
            expect(false)->toBeTrue('the second claim should not succeed while the first is open');
        } catch (CabinUnavailableException) {
            $observed = '409';
            DB::rollBack();
        } catch (QueryException $e) {
            $code = claimMysqlError($e);
            expect($code)->not->toBe(1213);
            expect(in_array($code, [1205, 1062], true))->toBeTrue();
            $observed = (string) $code;
            DB::rollBack();
        }
    });

    onClaimConnection('mysql', function (): void {
        DB::commit();
    });

    expect(CabinClaim::query()->whereNull('released_at')->where('cabin_id', $cabin->id)->count())->toBe(1);
    expect($observed)->not->toBe('none');
    fwrite(STDOUT, "free-cabin concurrency observed: {$observed}\n");
});

test('two claimers of the same expired hold never deadlock or double-occupy', function (): void {
    ['departure' => $departure, 'cabin' => $cabin] = concurrencyCabin();
    $expired = ClaimHolder::query()->create(['reference' => 'OLD', 'name' => 'Old']);
    $first = ClaimHolder::query()->create(['reference' => 'N1', 'name' => 'New one']);
    $second = ClaimHolder::query()->create(['reference' => 'N2', 'name' => 'New two']);
    $observed = 'none';

    DB::transaction(function () use ($departure, $cabin, $expired): void {
        app(ClaimService::class)->claim(
            $departure,
            collect([$cabin]),
            $expired,
            ClaimKind::Hold,
            HoldType::Web,
            now()->addMinutes(20),
        );
    });

    CabinClaim::query()->where('holder_id', $expired->id)->update([
        'expires_at' => now()->subMinute(),
    ]);

    onClaimConnection('mysql', function () use ($departure, $cabin, $first): void {
        DB::beginTransaction();
        app(ClaimService::class)->claim($departure, collect([$cabin]), $first, ClaimKind::Block);
    });

    onClaimConnection('mysql_lock', function () use ($departure, $cabin, $second, &$observed): void {
        DB::beginTransaction();

        try {
            app(ClaimService::class)->claim($departure, collect([$cabin]), $second, ClaimKind::Block);
            expect(false)->toBeTrue('the second claim should not succeed while the first is open');
        } catch (CabinUnavailableException) {
            $observed = '409';
            DB::rollBack();
        } catch (QueryException $e) {
            $code = claimMysqlError($e);
            expect($code)->not->toBe(1213);
            expect(in_array($code, [1205, 1062], true))->toBeTrue();
            $observed = (string) $code;
            DB::rollBack();
        }
    });

    onClaimConnection('mysql', function (): void {
        DB::commit();
    });

    expect(CabinClaim::query()->whereNull('released_at')->where('cabin_id', $cabin->id)->count())->toBe(1);
    expect($observed)->not->toBe('none');
    fwrite(STDOUT, "expired-hold concurrency observed: {$observed}\n");
});
