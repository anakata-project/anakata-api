<?php

declare(strict_types=1);

use App\Support\Rounding;

test('half-up rounds .5 away from zero on positives', function (): void {
    expect(Rounding::halfUp(0.5))->toBe(1);
    expect(Rounding::halfUp(1.5))->toBe(2);
    expect(Rounding::halfUp(2.5))->toBe(3);
    expect(Rounding::halfUp(2327.5))->toBe(2328);
});

test('half-up leaves integers and typical below-half values', function (): void {
    expect(Rounding::halfUp(10.0))->toBe(10);
    expect(Rounding::halfUp(10.4))->toBe(10);
    expect(Rounding::halfUp(10.6))->toBe(11);
});
