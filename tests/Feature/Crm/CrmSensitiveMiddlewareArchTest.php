<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

test('every api/crm route carries the crm.sensitive middleware', function (): void {
    $routes = collect(RouteFacade::getRoutes())->filter(
        fn (Route $route): bool => str_starts_with($route->uri(), 'api/crm'),
    );

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        expect($route->gatherMiddleware())->toContain('crm.sensitive');
    }
});
