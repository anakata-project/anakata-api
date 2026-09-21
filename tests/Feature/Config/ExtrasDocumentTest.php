<?php

declare(strict_types=1);

use App\Support\Config\Documents\ExtrasDocument;
use Illuminate\Support\Facades\Validator;

test('rules reject a missing items list, a blank code and a negative price', function (): void {
    $errors = Validator::make([], ExtrasDocument::rules())->errors();
    expect($errors->has('items'))->toBeTrue();

    $document = extrasDocument();
    $document['items'][0]['code'] = '';
    $document['items'][1]['price_usd'] = -1;

    $errors = Validator::make($document, ExtrasDocument::rules())->errors();
    expect($errors->has('items.0.code'))->toBeTrue();
    expect($errors->has('items.1.price_usd'))->toBeTrue();
});

test('rules reject duplicate codes', function (): void {
    $document = extrasDocument();
    $document['items'][1]['code'] = 'FLT';

    $errors = Validator::make($document, ExtrasDocument::rules())->errors();
    expect($errors->has('items.1.code'))->toBeTrue();
});

test('fromArray is lenient for a missing items key', function (): void {
    $document = ExtrasDocument::fromArray([]);

    expect($document->items)->toBe([]);
});

test('publishErrors refuse a dropped or renamed code and allow a name change', function (): void {
    $published = ExtrasDocument::fromArray(ExtrasDocument::initial());

    $dropped = extrasDocument();
    array_shift($dropped['items']);
    $errors = ExtrasDocument::fromArray($dropped)->publishErrors($published);
    expect($errors)->toHaveKey('items.0.code');

    $renamed = extrasDocument();
    $renamed['items'][0]['code'] = 'FLX';
    $errors = ExtrasDocument::fromArray($renamed)->publishErrors($published);
    expect($errors)->toHaveKey('items.0.code');

    $renamedName = extrasDocument();
    $renamedName['items'][0]['name'] = 'Domestic flights (updated)';
    expect(ExtrasDocument::fromArray($renamedName)->publishErrors($published))->toBe([]);
});
