<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Support\Contacts\PhoneNumber;

test('email is case-folded and trimmed and plus-addressing is kept distinct', function (): void {
    expect(Contact::normalizeEmail('  Ana@X.TEST  '))->toBe('ana@x.test');
    expect(Contact::normalizeEmail('ana+trip@x.test'))->toBe('ana+trip@x.test');
    expect(Contact::normalizeEmail('ana+trip@x.test'))->not->toBe(Contact::normalizeEmail('ana@x.test'));
    expect(Contact::normalizeEmail('   '))->toBeNull();
});

test('phone formats in several countries become the same E.164', function (): void {
    expect(PhoneNumber::toE164('(650) 253-0000', 'US'))->toBe('+16502530000');
    expect(PhoneNumber::toE164('6502530000', 'US'))->toBe('+16502530000');
    expect(PhoneNumber::toE164('+1 650 253 0000', 'EC'))->toBe('+16502530000');

    expect(PhoneNumber::toE164('02 394 5000', 'EC'))->toBe(PhoneNumber::toE164('+593 2 394 5000', null));
    expect(PhoneNumber::toE164('020 7031 3000', 'GB'))->toBe(PhoneNumber::toE164('+44 20 7031 3000', 'US'));
    expect(PhoneNumber::toE164('030 2270 0000', 'DE'))->toBe(PhoneNumber::toE164('+49 30 2270 0000', null));
});

test('an unparseable phone stays null', function (): void {
    expect(PhoneNumber::toE164('not a number', 'US'))->toBeNull();
    expect(PhoneNumber::toE164('253-0000', null))->toBeNull();
    expect(PhoneNumber::toE164('   ', 'US'))->toBeNull();
});

test('name normalisation collapses case and whitespace', function (): void {
    expect(Contact::normalizeName('  Ada   Lovelace '))->toBe('ada lovelace');
});
