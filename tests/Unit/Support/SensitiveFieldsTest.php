<?php

declare(strict_types=1);

use App\Support\SensitiveFields;

test('registry lists the initial sensitive field names', function (): void {
    expect(SensitiveFields::all())->toBe([
        'passport_no',
        'medical_note',
        'dietary_note',
        'accessibility_note',
        'dob',
        'nationality',
    ]);
});

test('keysIn finds nested sensitive keys', function (): void {
    $payload = [
        'name' => 'Ada',
        'guest' => [
            'passport_no' => 'X123',
            'notes' => [
                'medical_note' => 'none',
            ],
        ],
        'party' => [
            ['nationality' => 'US'],
            ['dob' => '1990-01-01'],
        ],
    ];

    expect(SensitiveFields::keysIn($payload))->toEqualCanonicalizing([
        'passport_no',
        'medical_note',
        'nationality',
        'dob',
    ]);
});

test('strip removes nested sensitive keys and leaves the rest', function (): void {
    $payload = [
        'name' => 'Ada',
        'guest' => [
            'passport_no' => 'X123',
            'email' => 'ada@example.com',
        ],
    ];

    expect(SensitiveFields::strip($payload))->toBe([
        'name' => 'Ada',
        'guest' => [
            'email' => 'ada@example.com',
        ],
    ]);
});

test('keysIn and strip ignore non-array payloads', function (): void {
    expect(SensitiveFields::keysIn('passport_no'))->toBe([]);
    expect(SensitiveFields::strip('passport_no'))->toBe('passport_no');
});
