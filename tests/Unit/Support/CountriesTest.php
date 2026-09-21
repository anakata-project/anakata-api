<?php

declare(strict_types=1);

use App\Support\Countries;

test('seeded guest codes resolve to English short names', function (string $code, string $name): void {
    expect(Countries::isValid($code))->toBeTrue();
    expect(Countries::name($code))->toBe($name);
})->with([
    ['US', 'United States of America'],
    ['DE', 'Germany'],
    ['SE', 'Sweden'],
    ['GB', 'United Kingdom'],
    ['AR', 'Argentina'],
    ['EC', 'Ecuador'],
    ['FR', 'France'],
    ['CO', 'Colombia'],
    ['NL', 'Netherlands'],
]);

test('unknown codes are not valid and name falls back to the code', function (): void {
    expect(Countries::isValid('ZZ'))->toBeFalse();
    expect(Countries::isValid('XX'))->toBeFalse();
    expect(Countries::name('ZZ'))->toBe('ZZ');
});

test('codes are unique ISO-3166-1 alpha-2 values', function (): void {
    $codes = Countries::codes();

    expect($codes)->not->toBeEmpty();
    expect($codes)->toHaveCount(count(array_unique($codes)));
    expect($codes)->each->toMatch('/^[A-Z]{2}$/');
});
