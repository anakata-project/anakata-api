<?php

declare(strict_types=1);

use App\Providers\TelescopeServiceProvider;
use App\Support\SensitiveFields;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Telescope\Console\PruneCommand;

test('telescope is disabled during tests', function (): void {
    expect((bool) config('telescope.enabled'))->toBeFalse();
});

test('telescope hides guest-sensitive request and response fields', function (): void {
    $hidden = TelescopeServiceProvider::hiddenRequestParameters();

    expect($hidden)->toContain('_token', 'password', 'password_confirmation', 'remember_token');

    foreach (SensitiveFields::all() as $field) {
        expect($hidden)->toContain($field);
    }
});

test('telescope hides cookie and authorization headers', function (): void {
    expect(TelescopeServiceProvider::hiddenRequestHeaders())->toContain(
        'cookie',
        'x-csrf-token',
        'x-xsrf-token',
        'authorization',
    );
});

test('telescope routes are not registered outside local', function (): void {
    $this->get('/telescope')->assertNotFound();
});

test('telescope prune is scheduled daily when the package is installed', function (): void {
    expect(class_exists(PruneCommand::class))->toBeTrue();

    $event = collect(app(Schedule::class)->events())->first(
        fn ($scheduled): bool => str_contains((string) ($scheduled->command ?? ''), 'telescope:prune'),
    );

    expect($event)->not->toBeNull();
    expect($event?->expression)->toBe('0 0 * * *');
});
