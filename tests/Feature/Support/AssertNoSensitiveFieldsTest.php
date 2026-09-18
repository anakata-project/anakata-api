<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\AssertionFailedError;

test('assertNoSensitiveFields passes on a clean json response', function (): void {
    $response = TestResponse::fromBaseResponse(new JsonResponse([
        'name' => 'Ada',
        'email' => 'ada@example.com',
    ]));

    assertNoSensitiveFields($response);
});

test('assertNoSensitiveFields fails when a sensitive key is present', function (): void {
    $response = TestResponse::fromBaseResponse(new JsonResponse([
        'name' => 'Ada',
        'passport_no' => 'X123',
    ]));

    expect(fn () => assertNoSensitiveFields($response))
        ->toThrow(AssertionFailedError::class);
});
