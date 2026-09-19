<?php

declare(strict_types=1);

use App\Support\Config\Documents\BusinessRulesDocument;

$document = BusinessRulesDocument::fromArray(BusinessRulesDocument::initial());

test('penaltyFor matches the seeded bands', function (int $days, int $pct) use ($document): void {
    expect($document->penaltyFor($days)->penaltyPct)->toBe($pct);
})->with([
    [130, 5],
    [120, 5],
    [119, 50],
    [90, 50],
    [89, 100],
    [0, 100],
]);
