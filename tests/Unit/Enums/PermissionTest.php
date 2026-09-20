<?php

declare(strict_types=1);

use App\Enums\Permission;

test('every case has a label and a group', function (): void {
    foreach (Permission::cases() as $permission) {
        expect($permission->label())->not->toBeEmpty();
        expect($permission->group())->not->toBeEmpty();
        expect(Permission::groupLabel($permission->group()))->not->toBeEmpty();
    }
});

test('values are unique and match the permission pattern', function (): void {
    $values = array_map(fn (Permission $permission): string => $permission->value, Permission::cases());

    expect($values)->toHaveCount(count(array_unique($values)));

    foreach ($values as $value) {
        expect($value)->toMatch('/^[a-z_]+\.[a-z_]+$/');
    }
});

test('the sprint 0 stub values still exist', function (): void {
    $stub = [
        'bookings.view_all',
        'bookings.create',
        'bookings.change_status',
        'bookings.move',
        'bookings.delete',
        'users.manage',
        'payments.record',
        'payments.mark_wire_received',
        'refunds.execute',
        'commissions.override_cap',
        'bookings.overdue_decision',
        'refunds.approve',
        'rates.manage',
        'rules.manage',
    ];

    foreach ($stub as $value) {
        expect(Permission::tryFrom($value))->not->toBeNull();
    }
});

test('isFlag is true only for finance and director groups', function (): void {
    foreach (Permission::cases() as $permission) {
        expect($permission->isFlag())->toBe(in_array($permission->group(), ['finance', 'director'], true));
    }
});
