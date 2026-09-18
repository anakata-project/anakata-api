<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;

beforeEach(function (): void {
    Route::middleware(['api', 'crm.sensitive'])
        ->prefix('api/crm')
        ->get('/probe', fn () => response()->json([
            'name' => 'Ada',
            'passport_no' => 'X123',
        ]));
});

test('throws in testing when a sensitive field is present', function (): void {
    $this->withoutExceptionHandling();

    $this->getJson('/api/crm/probe');
})->throws(RuntimeException::class, 'CRM response contained sensitive fields: passport_no');

test('strips sensitive fields and logs in production', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    Log::spy();

    $this->getJson('/api/crm/probe')
        ->assertOk()
        ->assertJsonMissingPath('passport_no')
        ->assertJsonPath('name', 'Ada');

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'sensitive fields')
                && $context['fields'] === ['passport_no'];
        });
});
