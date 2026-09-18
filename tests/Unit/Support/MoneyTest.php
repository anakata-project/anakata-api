<?php

declare(strict_types=1);

use App\Support\Money;

test('format renders whole usd with a thousands separator', function (): void {
    expect(Money::format(28520))->toBe('USD 28,520');
    expect(Money::format(26600))->toBe('USD 26,600');
});

test('formatCents renders cents as usd with two decimals', function (): void {
    expect(Money::formatCents(2_852_000))->toBe('USD 28,520.00');
});
