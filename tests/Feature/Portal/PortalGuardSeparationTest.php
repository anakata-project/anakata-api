<?php

declare(strict_types=1);

use Database\Seeders\ConfigSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('every protected api/portal route carries the portal.auth middleware and not the staff guard', function (): void {
    $protected = collect(RouteFacade::getRoutes())->filter(
        fn (Route $route): bool => str_starts_with($route->uri(), 'api/portal')
            && in_array($route->uri(), ['api/portal/auth/logout', 'api/portal/auth/me'], true),
    );

    expect($protected)->not->toBeEmpty();

    foreach ($protected as $route) {
        expect($route->gatherMiddleware())->toContain('portal.auth');
        expect($route->gatherMiddleware())->not->toContain('auth:sanctum');
        expect($route->gatherMiddleware())->not->toContain('active');
    }
});

test('no api/rms, api/crm or api/privacy route carries the portal.auth middleware', function (): void {
    $routes = collect(RouteFacade::getRoutes())->filter(
        fn (Route $route): bool => str_starts_with($route->uri(), 'api/rms')
            || str_starts_with($route->uri(), 'api/crm')
            || str_starts_with($route->uri(), 'api/privacy'),
    );

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        expect($route->gatherMiddleware())->not->toContain('portal.auth');
    }
});

test('a staff session is refused by every protected api/portal route', function (): void {
    $this->actingAs(adminUser())
        ->withHeaders(portalHeaders())
        ->getJson('/api/portal/auth/me')
        ->assertUnauthorized();

    $this->actingAs(adminUser())
        ->withHeaders(portalHeaders())
        ->postJson('/api/portal/auth/logout')
        ->assertUnauthorized();
});

test('an agency session is refused by every rms, crm and privacy route it tries', function (): void {
    $user = agencyUser();

    $this->actingAs($user, 'agency')
        ->withHeaders(panelHeaders())
        ->getJson('/api/rms/agencies')
        ->assertUnauthorized();

    $this->actingAs($user, 'agency')
        ->withHeaders(panelHeaders())
        ->getJson('/api/crm/contacts')
        ->assertUnauthorized();

    $this->actingAs($user, 'agency')
        ->withHeaders(panelHeaders())
        ->getJson('/api/privacy/requests')
        ->assertUnauthorized();
});
