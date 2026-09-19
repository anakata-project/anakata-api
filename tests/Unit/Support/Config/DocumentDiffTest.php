<?php

declare(strict_types=1);

use App\Support\Config\DocumentDiff;

test('associative objects recurse to leaf paths', function (): void {
    $changes = DocumentDiff::compare(
        ['terms' => ['cabin_deposit_pct' => 10], 'bands' => [['min' => 120, 'pct' => 5]]],
        ['terms' => ['cabin_deposit_pct' => 15], 'bands' => [['min' => 120, 'pct' => 5], ['min' => 0, 'pct' => 100]]],
        [
            'terms.cabin_deposit_pct' => 'Cabin deposit %',
            'bands' => 'Cancellation bands',
        ],
    );

    expect($changes)->toHaveCount(2);
    expect($changes[0]->path)->toBe('terms.cabin_deposit_pct');
    expect($changes[0]->label)->toBe('Cabin deposit %');
    expect($changes[0]->from)->toBe(10);
    expect($changes[0]->to)->toBe(15);
    expect($changes[1]->path)->toBe('bands');
    expect($changes[1]->label)->toBe('Cancellation bands');
    expect($changes[1]->from)->toBe([['min' => 120, 'pct' => 5]]);
    expect($changes[1]->to)->toBe([['min' => 120, 'pct' => 5], ['min' => 0, 'pct' => 100]]);
});

test('object key order does not count as a change', function (): void {
    $changes = DocumentDiff::compare(
        ['title' => 'A', 'terms' => ['cabin_deposit_pct' => 10, 'note' => 'x']],
        ['terms' => ['note' => 'x', 'cabin_deposit_pct' => 10], 'title' => 'A'],
        [],
    );

    expect($changes)->toBe([]);
});

test('an unlabelled path falls back to the path itself', function (): void {
    $changes = DocumentDiff::compare(
        ['title' => 'Old'],
        ['title' => 'New'],
        [],
    );

    expect($changes)->toHaveCount(1);
    expect($changes[0]->label)->toBe('title');
});
