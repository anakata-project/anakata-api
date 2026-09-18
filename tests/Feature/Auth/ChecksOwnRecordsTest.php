<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnRecords;
use Tests\Fixtures\OwnedRecord;

function ownRecordsChecker(): object
{
    return new class
    {
        use ChecksOwnRecords;

        public function check(User $user, OwnedRecord $record, string $ownerColumn = 'owner_id'): bool
        {
            return $this->ownsOrMayActOnAny($user, $record, $ownerColumn);
        }
    };
}

test('the owner may act on their record including a string owner id', function (): void {
    $owner = User::factory()->create();
    $record = new OwnedRecord(['owner_id' => (string) $owner->id]);

    expect(ownRecordsChecker()->check($owner, $record))->toBeTrue();
});

test('another user may not act on a record they do not own', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $record = new OwnedRecord(['owner_id' => $owner->id]);

    expect(ownRecordsChecker()->check($other, $record))->toBeFalse();
});

test('a user with records.act_on_any may act on any record', function (): void {
    $role = Role::factory()->create([
        'permissions' => [Permission::RecordsActOnAny],
    ]);
    $actor = User::factory()->create(['role_id' => $role->id]);
    $owner = User::factory()->create();
    $record = new OwnedRecord(['owner_id' => $owner->id]);

    expect(ownRecordsChecker()->check($actor, $record))->toBeTrue();
});

test('a null owner is not treated as owned', function (): void {
    $user = User::factory()->create();
    $record = new OwnedRecord(['owner_id' => null]);

    expect(ownRecordsChecker()->check($user, $record))->toBeFalse();
});
