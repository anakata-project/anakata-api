<?php

declare(strict_types=1);

use App\Services\Pricing\CabinCategory;
use App\Services\Pricing\CabinPricer;
use App\Services\Pricing\NoRate;
use App\Services\Pricing\Quote;
use App\Services\Pricing\QuoteInput;
use App\Services\Pricing\QuoteLine;
use App\Services\Pricing\QuoteType;
use App\Support\Config\Documents\RatesDocument;

function publishedRates(): RatesDocument
{
    return RatesDocument::fromArray(RatesDocument::initial());
}

function quoteLine(Quote $quote, string $code): ?QuoteLine
{
    foreach ($quote->lines as $line) {
        if ($line->code === $code) {
            return $line;
        }
    }

    return null;
}

test('the eight reference prices and deposits are exact', function (QuoteInput $input, int $total, int $deposit): void {
    $quote = (new CabinPricer)->quote(publishedRates(), $input);

    expect($quote)->toBeInstanceOf(Quote::class);
    expect($quote->total)->toBe($total);
    expect($quote->deposit)->toBe($deposit);
})->with([
    'Suite, 2 adults' => [
        new QuoteInput(2027, QuoteType::Cabin, CabinCategory::Suite, 2, 0),
        26600,
        2660,
    ],
    'Suite, 1 adult (single)' => [
        new QuoteInput(2027, QuoteType::Cabin, CabinCategory::Suite, 1, 0),
        23275,
        2328,
    ],
    'Suite, 3 adults (triple)' => [
        new QuoteInput(2027, QuoteType::Cabin, CabinCategory::Suite, 3, 0),
        35910,
        3591,
    ],
    'Suite, 2 adults + 1 child' => [
        new QuoteInput(2027, QuoteType::Cabin, CabinCategory::Suite, 2, 1),
        37905,
        3791,
    ],
    "Owner's Suite, 2 adults" => [
        new QuoteInput(2027, QuoteType::Cabin, CabinCategory::Owner, 2, 0),
        50000,
        5000,
    ],
    'Suite, 2 adults, festive' => [
        new QuoteInput(2027, QuoteType::Cabin, CabinCategory::Suite, 2, 0, festive: true),
        28100,
        2810,
    ],
    'Charter' => [
        new QuoteInput(2027, QuoteType::Charter),
        199500,
        39900,
    ],
    'Charter, festive' => [
        new QuoteInput(2027, QuoteType::Charter, festive: true),
        211500,
        42300,
    ],
]);

test('two adults and two children apply the per-cabin child cap', function (): void {
    $quote = (new CabinPricer)->quote(
        publishedRates(),
        new QuoteInput(2027, QuoteType::Cabin, CabinCategory::Suite, 2, 2),
    );

    expect($quote)->toBeInstanceOf(Quote::class);
    expect($quote->total)->toBe(49210);
    $child = quoteLine($quote, 'child_discount');
    expect($child?->label)->toBe('Child discount −15% ppdo × 2');
    expect($child?->amount)->toBe(-3990);
});

test('one adult and two children apply the per-adult child cap', function (): void {
    $quote = (new CabinPricer)->quote(
        publishedRates(),
        new QuoteInput(2027, QuoteType::Cabin, CabinCategory::Suite, 1, 2),
    );

    expect($quote)->toBeInstanceOf(Quote::class);
    expect($quote->total)->toBe(37905);
    $child = quoteLine($quote, 'child_discount');
    expect($child?->label)->toBe('Child discount −15% ppdo × 1');
    expect(quoteLine($quote, 'triple_discount'))->toBeNull();
});

test('three guests including a child do not get the triple discount', function (): void {
    $quote = (new CabinPricer)->quote(
        publishedRates(),
        new QuoteInput(2027, QuoteType::Cabin, CabinCategory::Suite, 2, 1),
    );

    expect($quote)->toBeInstanceOf(Quote::class);
    expect(quoteLine($quote, 'child_discount'))->not->toBeNull();
    expect(quoteLine($quote, 'triple_discount'))->toBeNull();
});

test('back-to-back applies last on the cabin running total', function (): void {
    $quote = (new CabinPricer)->quote(
        publishedRates(),
        new QuoteInput(2027, QuoteType::Cabin, CabinCategory::Suite, 2, 0, backToBack: true),
    );

    expect($quote)->toBeInstanceOf(Quote::class);
    expect($quote->total)->toBe(25270);
    $line = $quote->lines[array_key_last($quote->lines)];
    expect($line->code)->toBe('back_to_back');
    expect($line->label)->toBe('Back-to-back −5%');
    expect($line->amount)->toBe(-1330);
});

test('back-to-back is not applied on a festive cabin', function (): void {
    $quote = (new CabinPricer)->quote(
        publishedRates(),
        new QuoteInput(2027, QuoteType::Cabin, CabinCategory::Suite, 2, 0, festive: true, backToBack: true),
    );

    expect($quote)->toBeInstanceOf(Quote::class);
    expect($quote->total)->toBe(28100);
    expect(quoteLine($quote, 'back_to_back'))->toBeNull();
});

test('an unknown year is NoRate', function (): void {
    $quote = (new CabinPricer)->quote(
        publishedRates(),
        new QuoteInput(2031, QuoteType::Cabin, CabinCategory::Suite, 2, 0),
    );

    expect($quote)->toBeInstanceOf(NoRate::class);
    expect($quote->reason)->toBe('No 2031 Suite rate');
});
