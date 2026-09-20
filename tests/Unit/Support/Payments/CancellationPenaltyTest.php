<?php

declare(strict_types=1);

use App\Support\Config\Documents\BusinessRulesDocument;
use App\Support\Payments\CancellationPenalty;

$bands = BusinessRulesDocument::fromArray(BusinessRulesDocument::initial())->bands;

test('bandFor lands on the configured boundaries', function (int $days, int $min, int $pct) use ($bands): void {
    expect(CancellationPenalty::bandFor($days, $bands))->toBe([
        'min_days' => $min,
        'penalty_pct' => $pct,
    ]);
})->with([
    [120, 120, 5],
    [119, 90, 50],
    [90, 90, 50],
    [89, 0, 100],
    [0, 0, 100],
    [484, 120, 5],
]);

test('labels are built from neighbouring bands', function () use ($bands): void {
    expect(CancellationPenalty::label(['min_days' => 120, 'penalty_pct' => 5], $bands))->toBe('≥120 days');
    expect(CancellationPenalty::label(['min_days' => 90, 'penalty_pct' => 50], $bands))->toBe('90–119 days');
    expect(CancellationPenalty::label(['min_days' => 0, 'penalty_pct' => 100], $bands))->toBe('0–89 days');
});

test('adding a band changes the labels without code', function (): void {
    $bands = [
        ['min_days' => 180, 'penalty_pct' => 0],
        ['min_days' => 120, 'penalty_pct' => 5],
        ['min_days' => 90, 'penalty_pct' => 50],
        ['min_days' => 0, 'penalty_pct' => 100],
    ];

    expect(CancellationPenalty::label(['min_days' => 180, 'penalty_pct' => 0], $bands))->toBe('≥180 days');
    expect(CancellationPenalty::label(['min_days' => 120, 'penalty_pct' => 5], $bands))->toBe('120–179 days');
    expect(CancellationPenalty::label(['min_days' => 90, 'penalty_pct' => 50], $bands))->toBe('90–119 days');
});

test('penalty uses half-up on the booking total', function (): void {
    expect(CancellationPenalty::penalty(26600, 5))->toBe(1330);
    expect(CancellationPenalty::penalty(26600, 50))->toBe(13300);
    expect(CancellationPenalty::penalty(26600, 100))->toBe(26600);
});

test('refund due is paid minus penalty and never below zero', function (): void {
    expect(CancellationPenalty::refundDue(2660, 1330))->toBe(1330);
    expect(CancellationPenalty::refundDue(26600, 13300))->toBe(13300);
    expect(CancellationPenalty::refundDue(2660, 26600))->toBe(0);
});
