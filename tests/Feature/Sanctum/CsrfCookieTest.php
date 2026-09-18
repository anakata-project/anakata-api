<?php

declare(strict_types=1);

test('csrf cookie from the panel origin sets XSRF-TOKEN and CORS headers', function (): void {
    $origin = 'http://localhost:3001';

    $response = $this->withHeaders([
        'Origin' => $origin,
    ])->get('/sanctum/csrf-cookie');

    $response->assertNoContent();
    $response->assertCookie('XSRF-TOKEN');
    $response->assertHeader('Access-Control-Allow-Origin', $origin);
    $response->assertHeader('Access-Control-Allow-Credentials', 'true');
});
