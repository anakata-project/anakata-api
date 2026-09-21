<?php

declare(strict_types=1);

use App\Support\SensitiveFields;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use ReflectionProperty;
use RuntimeException;

test('sensitive fields are in the exception handler dontFlash list', function (): void {
    $handler = app(ExceptionHandler::class);
    $property = new ReflectionProperty($handler, 'dontFlash');
    /** @var list<string> $dontFlash */
    $dontFlash = $property->getValue($handler);

    foreach (SensitiveFields::all() as $key) {
        expect($dontFlash)->toContain($key);
    }
});

test('a failing request does not write a passport number to the log', function (): void {
    Event::fake([MessageLogged::class]);

    Route::middleware('api')->post('/api/rms/probe-sensitive-log', function (): never {
        throw new RuntimeException('probe failed');
    });

    $this->postJson('/api/rms/probe-sensitive-log', [
        'passport_no' => 'UNIQUE-PP-LEAK-TEST-ZX9',
        'name' => 'Ada',
    ])->assertStatus(500);

    $payload = json_encode(Event::dispatched(MessageLogged::class));

    expect($payload)->toBeString();
    expect($payload)->not->toContain('UNIQUE-PP-LEAK-TEST-ZX9');

    $context = app(ExceptionHandler::class)->buildContextForException(new RuntimeException('probe failed'));
    expect(json_encode($context))->not->toContain('UNIQUE-PP-LEAK-TEST-ZX9');
    expect($context)->not->toHaveKey('passport_no');
});
