<?php

declare(strict_types=1);

use App\Support\Engine\PagePath;

test('complete reservation tokens are stored as a placeholder', function (): void {
    expect(PagePath::redact('/complete/abc123secret'))->toBe('/complete/[token]');
    expect(PagePath::redact('/complete/abc123/billing'))->toBe('/complete/[token]');
    expect(PagePath::redact('/complete/abc123?utm_source=google'))->toBe('/complete/[token]');
});

test('questionnaire and survey tokens are stored as placeholders', function (): void {
    expect(PagePath::redact('/questionnaire/secret-token'))->toBe('/questionnaire/[token]');
    expect(PagePath::redact('/questionnaire/secret-token?utm_source=mail'))->toBe('/questionnaire/[token]');
    expect(PagePath::redact('/survey/secret-token#thanks'))->toBe('/survey/[token]');
    expect(PagePath::redact('/survey/secret-token?utm_source=mail'))->toBe('/survey/[token]');
    expect(PagePath::redact('/charter-proposal/secret-token'))->toBe('/charter-proposal/[token]');
    expect(PagePath::redact('/charter-proposal/secret-token?utm_source=mail'))->toBe('/charter-proposal/[token]');
    expect(PagePath::redact('/unsubscribe/secret-token'))->toBe('/unsubscribe/[token]');
    expect(PagePath::redact('/unsubscribe/secret-token?utm_source=mail'))->toBe('/unsubscribe/[token]');
});

test('ordinary paths keep the path and drop the query string', function (): void {
    expect(PagePath::redact('/itineraries/western-realm'))->toBe('/itineraries/western-realm');
    expect(PagePath::redact('/itineraries/western-realm?utm_source=google'))->toBe('/itineraries/western-realm');
});
