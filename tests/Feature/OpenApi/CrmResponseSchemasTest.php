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

/**
 * @param  array<string, mixed>  $node
 */
function crmSchemaRef(array $node, string $name): void
{
    $ref = $node['$ref'] ?? $node['allOf'][0]['$ref'] ?? $node['items']['$ref'] ?? $node['items']['allOf'][0]['$ref'] ?? null;

    expect($ref)->toBeString("{$name} is not a \$ref");
    expect($ref)->toContain($name);
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
        'CrmContactResource',
        'ContactBookingResource',
        'ContactDuplicateResource',
        'ContactMergeResource',
        'ContactMergeResultResource',
        'ContactUnmergeResultResource',
        'ContactTimelineItemResource',
        'EngineActivityItemResource',
        'FieldOwnershipResource',
        'ScheduledJobResource',
        'SyncFailureResource',
        'SyncIdentityResource',
        'EventCatalogueResource',
        'RetrySyncFailureResource',
        'ConsentRegisterRowResource',
        'ConsentDataMapRowResource',
        'ContactConsentsResource',
        'PipelineResource',
        'StageMapResource',
        'DealResource',
        'TaskListResource',
        'ContactActivityResource',
        'SubjectRequestResource',
        'CampaignIndexResource',
        'CampaignOffersResource',
        'CampaignBookingPageResource',
        'AttributionModelResource',
        'DeliveryIndexResource',
    ];

    foreach ($expected as $name) {
        expect($names)->toContain($name);
        crmOpenApiSchema($spec, $name);
    }

    foreach (['ContactType', 'ContactLifecycle'] as $enum) {
        expect($names)->toContain($enum);
        expect($spec['components']['schemas'][$enum]['enum'] ?? null)->toBeArray();
        expect($spec['components']['schemas'][$enum]['enum'])->not->toBeEmpty();
    }

    $contact = crmOpenApiSchema($spec, 'CrmContactResource');
    expect($contact['properties'])->toHaveKeys([
        'id',
        'name',
        'email',
        'phone',
        'phone_e164',
        'country',
        'language',
        'preferred_channel',
        'type',
        'first_touch',
        'last_touch',
        'lifetime_value',
        'segment',
        'lifecycle',
        'nps',
        'consent',
        'main_channel',
        'channel_of_origin',
        'resolved_from_alias',
        'alias_id',
        'merge_id',
        'bookings',
    ]);
    foreach ([
        'passport_no',
        'dob',
        'nationality',
        'medical_note',
        'dietary_note',
        'accessibility_note',
    ] as $sensitive) {
        expect($contact['properties'])->not->toHaveKey($sensitive);
    }
    crmSchemaRef($contact['properties']['bookings'] ?? [], 'ContactBookingResource');

    $touch = $contact['properties']['first_touch']['properties']
        ?? $contact['properties']['first_touch']['anyOf'][0]['properties']
        ?? [];
    expect($touch)->toHaveKeys([
        'source',
        'medium',
        'campaign',
        'content',
        'term',
        'landing_path',
        'captured_at',
    ]);

    $duplicate = crmOpenApiSchema($spec, 'ContactDuplicateResource');
    crmSchemaRef($duplicate['properties']['a'] ?? [], 'CrmContactResource');
    crmSchemaRef($duplicate['properties']['b'] ?? [], 'CrmContactResource');

    $merged = crmOpenApiSchema($spec, 'ContactMergeResultResource');
    crmSchemaRef($merged['properties']['merge'] ?? [], 'ContactMergeResource');
    crmSchemaRef($merged['properties']['contact'] ?? [], 'CrmContactResource');

    $unmerged = crmOpenApiSchema($spec, 'ContactUnmergeResultResource');
    crmSchemaRef($unmerged['properties']['merge'] ?? [], 'ContactMergeResource');

    $contacts = $spec['paths']['/crm/contacts']['get']
        ?? $spec['paths']['/api/crm/contacts']['get']
        ?? null;
    expect($contacts)->toBeArray();
    $contactsSchema = $contacts['responses']['200']['content']['application/json']['schema'] ?? [];
    $filters = $contactsSchema['properties']['meta']['properties']['filters']['properties'] ?? [];
    expect($filters)->toHaveKeys(['type', 'lifecycle']);

    $activity = $spec['paths']['/crm/activity']['get']
        ?? $spec['paths']['/api/crm/activity']['get']
        ?? null;
    expect($activity)->toBeArray();
    $kpis = $activity['responses']['200']['content']['application/json']['schema']['properties']['meta']['properties']['kpis']['properties'] ?? [];
    expect($kpis)->toHaveKeys([
        'events_today',
        'identified',
        'anonymous',
        'inventory_touching',
        'web_hold_minutes',
        'web_hold_extension_minutes',
    ]);

    $activityItem = crmOpenApiSchema($spec, 'EngineActivityItemResource');
    expect($activityItem['properties'])->toHaveKey('contact_id');
    $contactIdSchema = $activityItem['properties']['contact_id'];
    $contactIdNullable = ($contactIdSchema['nullable'] ?? false) === true
        || (is_array($contactIdSchema['type'] ?? null) && in_array('null', $contactIdSchema['type'], true));
    expect($contactIdNullable)->toBeTrue();

    $contactUpdate = $spec['paths']['/crm/contacts/{contact}']['patch']
        ?? $spec['paths']['/api/crm/contacts/{contact}']['patch']
        ?? null;
    expect($contactUpdate)->toBeArray();
    $conflictResponse = $contactUpdate['responses']['409'] ?? null;
    expect($conflictResponse)->toBeArray();
    $conflictSchema = $conflictResponse['content']['application/json']['schema'] ?? [];
    if (isset($conflictResponse['$ref'])) {
        $conflictName = basename((string) $conflictResponse['$ref']);
        $conflictSchema = $spec['components']['responses'][$conflictName]['content']['application/json']['schema'] ?? [];
    }
    expect($conflictSchema['properties'] ?? [])->toHaveKeys(['message', 'conflicting_contact']);

    $jobs = $spec['paths']['/crm/sync/jobs']['get']
        ?? $spec['paths']['/api/crm/sync/jobs']['get']
        ?? null;
    expect($jobs)->toBeArray();
    $jobKpis = $jobs['responses']['200']['content']['application/json']['schema']['properties']['meta']['properties']['kpis']['properties'] ?? [];
    expect($jobKpis)->toHaveKeys(['jobs_failing', 'failures_open', 'merges_this_month']);

    $events = $spec['paths']['/crm/sync/events']['get']
        ?? $spec['paths']['/api/crm/sync/events']['get']
        ?? null;
    expect($events)->toBeArray();
    $note = $events['responses']['200']['content']['application/json']['schema']['properties']['meta']['properties']['note'] ?? [];
    expect($note['type'] ?? null)->toBe('string');

    $register = $spec['paths']['/crm/consents/register']['get']
        ?? $spec['paths']['/api/crm/consents/register']['get']
        ?? null;
    expect($register)->toBeArray();

    $consents = $spec['paths']['/crm/contacts/{contact}/consents']['get']
        ?? $spec['paths']['/api/crm/contacts/{contact}/consents']['get']
        ?? null;
    expect($consents)->toBeArray();

    $state = crmOpenApiSchema($spec, 'ContactConsentsResource');
    expect($state['properties'])->toHaveKeys(['current', 'history']);
    expect(json_encode($state))->not->toContain('"ip"');
});
