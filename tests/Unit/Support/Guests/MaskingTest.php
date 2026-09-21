<?php

declare(strict_types=1);

use App\Support\Guests\Masking;

test('passport is null when the value is missing', function (): void {
    expect(Masking::passport(null, false))->toBeNull();
    expect(Masking::passport(null, true))->toBeNull();
});

test('passport returns the full value when the viewer may see it', function (): void {
    expect(Masking::passport('AB1234567', true))->toBe('AB1234567');
    expect(Masking::passport('AB1', true))->toBe('AB1');
    expect(Masking::passport('', true))->toBeNull();
});

test('passport masks short values without trailing characters', function (string $value): void {
    expect(Masking::passport($value, false))->toBe('••••');
})->with([
    '0 characters' => [''],
    '3 characters' => ['AB1'],
    '5 characters' => ['AB123'],
]);

test('passport masks long values as bullets plus the last three characters', function (string $value, string $masked): void {
    expect(Masking::passport($value, false))->toBe($masked);
})->with([
    '6 characters' => ['AB1234', '•••• 234'],
    '9 characters' => ['AB1234567', '•••• 567'],
]);

test('note returns the text and on_file when the viewer may see it', function (): void {
    expect(Masking::note('peanut allergy', true))->toBe([
        'value' => 'peanut allergy',
        'on_file' => true,
    ]);
    expect(Masking::note(null, true))->toBe([
        'value' => null,
        'on_file' => false,
    ]);
    expect(Masking::note('', true))->toBe([
        'value' => null,
        'on_file' => false,
    ]);
});

test('note hides the text and keeps on_file when the viewer may not see it', function (): void {
    expect(Masking::note('peanut allergy', false))->toBe([
        'value' => null,
        'on_file' => true,
    ]);
    expect(Masking::note(null, false))->toBe([
        'value' => null,
        'on_file' => false,
    ]);
});
