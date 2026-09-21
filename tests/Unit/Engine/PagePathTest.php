<?php

declare(strict_types=1);

use App\Support\Engine\PagePath;

test('complete reservation tokens are stored as a placeholder', function (): void {
    expect(PagePath::redact('/complete/abc123secret'))->toBe('/complete/[token]');
    expect(PagePath::redact('/complete/abc123/billing'))->toBe('/complete/[token]');
    expect(PagePath::redact('/complete/abc123?utm_source=google'))->toBe('/complete/[token]');
});

test('ordinary paths keep the path and drop the query string', function (): void {
    expect(PagePath::redact('/itineraries/western-realm'))->toBe('/itineraries/western-realm');
    expect(PagePath::redact('/itineraries/western-realm?utm_source=google'))->toBe('/itineraries/western-realm');
});
