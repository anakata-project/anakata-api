<?php

declare(strict_types=1);

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Illuminate\Support\Facades\Gate;

/**
 * @param  array<string, mixed>  $spec
 * @return array<string, mixed>
 */
function crmOpenApiSchema(array $spec, string $name): array
{
    $schema = $spec['components']['schemas'][$name] ?? null;

    expect($schema)->toBeArray("schema {$name} is missing");

    if (! isset($schema['properties'])) {
        $arms = $schema['anyOf'] ?? $schema['oneOf'] ?? [];
        $schema = collect(is_array($arms) ? $arms : [])
            ->sortByDesc(fn (mixed $arm): int => is_array($arm) ? count($arm['properties'] ?? []) : 0)
            ->first() ?? $schema;
    }

    expect($schema['properties'] ?? null)->toBeArray("schema {$name} has no properties");
    expect($schema['properties'])->not->toBeEmpty("schema {$name} properties are empty");

    return $schema;
}

test('crm OpenAPI schemas have properties', function (): void {
    Gate::define('viewApiDocs', fn (): bool => true);

    $response = $this->withoutMiddleware(RestrictedDocsAccess::class)
        ->getJson('/docs/api.json')
        ->assertOk();

    /** @var array<string, mixed> $spec */
    $spec = $response->json();

    $names = array_keys($spec['components']['schemas'] ?? []);

    $expected = [
        'ContactDuplicateResource',
        'ContactMergeResource',
        'ContactMergeResultResource',
        'ContactUnmergeResultResource',
    ];

    foreach ($expected as $name) {
        expect($names)->toContain($name);
        crmOpenApiSchema($spec, $name);
    }

    $contactNames = array_values(array_filter(
        $names,
        fn (string $name): bool => str_contains($name, 'ContactResource'),
    ));

    expect($contactNames)->not->toBeEmpty();

    foreach ($contactNames as $name) {
        crmOpenApiSchema($spec, $name);
    }
});
