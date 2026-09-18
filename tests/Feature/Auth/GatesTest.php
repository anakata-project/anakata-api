<?php

declare(strict_types=1);

use App\Enums\Permission;
use Illuminate\Support\Facades\Gate;

test('every permission is registered as a gate ability', function (): void {
    foreach (Permission::cases() as $permission) {
        expect(Gate::has($permission->value))->toBeTrue();
    }
});
