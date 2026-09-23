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

    if (! is_string($ref)) {
        foreach (is_array($node['anyOf'] ?? null) ? $node['anyOf'] : [] as $arm) {
            if (! is_array($arm)) {
                continue;
            }

            $armRef = $arm['$ref'] ?? $arm['allOf'][0]['$ref'] ?? null;

            if (is_string($armRef) && str_contains($armRef, $name)) {
                $ref = $armRef;
                break;
            }
        }
    }

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
        'CrmJourneyResource',
        'CrmJourneyEnrolmentResource',
        'CrmSegmentResource',
        'CrmSegmentVocabularyResource',
        'CrmAutomationResource',
        'CrmMessageTemplateResource',
        'CrmMessageTemplateVersionResource',
        'CampaignIndexResource',
        'CampaignOffersResource',
        'CampaignBookingPageResource',
        'AttributionModelResource',
        'DeliveryIndexResource',
        'CrmConversationResource',
        'CrmMessageResource',
        'B2bPartnerResource',
    ];

    foreach ($expected as $name) {
        expect($names)->toContain($name);
        crmOpenApiSchema($spec, $name);
    }

    foreach ([
        'ContactType',
        'ContactLifecycle',
        'ContactSegment',
        'SegmentKind',
        'SegmentDimension',
        'AutomationKind',
        'AutomationAudience',
        'JourneyStepAction',
        'JourneyEnrolmentStatus',
        'ConversationStatus',
        'MessageDirection',
    ] as $enum) {
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
    crmSchemaRef($contact['properties']['segment'] ?? [], 'ContactSegment');

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
    $catalogue = $jobs['responses']['200']['content']['application/json']['schema']['properties']['meta']['properties']['catalogue']['items']['properties'] ?? [];
    expect($catalogue)->toHaveKeys(['job', 'command', 'sentence']);

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

    $segment = crmOpenApiSchema($spec, 'CrmSegmentResource');
    expect($segment['properties']['conditions']['properties']['items']['items']['properties'] ?? null)->toHaveKeys(['field', 'operator', 'value']);
    expect($segment['properties']['conditions']['properties']['items']['items']['additionalProperties'] ?? false)->not->toBeTrue();
    crmSchemaRef($segment['properties']['kind'] ?? [], 'SegmentKind');
    crmSchemaRef($segment['properties']['dimensions']['items']['properties']['axis'] ?? [], 'SegmentDimension');

    $vocabulary = crmOpenApiSchema($spec, 'CrmSegmentVocabularyResource');
    expect($vocabulary['properties']['data']['properties']['fields']['items']['properties'] ?? null)->toHaveKeys(['field', 'label', 'operators', 'value']);
    expect($vocabulary['properties']['data']['properties']['fields']['items']['additionalProperties'] ?? false)->not->toBeTrue();

    $automation = crmOpenApiSchema($spec, 'CrmAutomationResource');
    expect($automation)->not->toHaveKey('anyOf');
    crmSchemaRef($automation['properties']['audience'] ?? [], 'AutomationAudience');
    crmSchemaRef($automation['properties']['kind'] ?? [], 'AutomationKind');

    $journey = crmOpenApiSchema($spec, 'CrmJourneyResource');
    expect($journey['properties']['steps']['items']['properties'] ?? null)->toHaveKeys(['position', 'name', 'action', 'template_key']);
    expect($journey['properties']['steps']['items']['type'] ?? null)->not->toBe('string');
    crmSchemaRef($journey['properties']['kind'] ?? [], 'AutomationKind');
    crmSchemaRef($journey['properties']['steps']['items']['properties']['action'] ?? [], 'JourneyStepAction');

    $enrolment = crmOpenApiSchema($spec, 'CrmJourneyEnrolmentResource');
    expect($enrolment['properties']['sends']['items']['properties'] ?? null)->toHaveKeys(['sent_at', 'template_key', 'catalogue_key', 'delivery_id']);
    expect($enrolment['properties']['sends']['items']['type'] ?? null)->not->toBe('string');
    crmSchemaRef($enrolment['properties']['status'] ?? [], 'JourneyEnrolmentStatus');

    $template = crmOpenApiSchema($spec, 'CrmMessageTemplateResource');
    crmSchemaRef($template['properties']['kind'] ?? [], 'AutomationKind');
    crmSchemaRef($template['properties']['published'] ?? [], 'CrmMessageTemplateVersionResource');
    crmSchemaRef($template['properties']['draft'] ?? [], 'CrmMessageTemplateVersionResource');

    $version = crmOpenApiSchema($spec, 'CrmMessageTemplateVersionResource');
    expect($version['properties']['body']['properties'] ?? null)->toHaveKeys(['paragraphs', 'list', 'cta']);
    expect($version['properties']['body']['additionalProperties'] ?? false)->not->toBeTrue();

    $testSend = $spec['paths']['/crm/templates/{template}/test-send']['post']
        ?? $spec['paths']['/api/crm/templates/{template}/test-send']['post']
        ?? null;
    expect($testSend)->toBeArray();
    crmSchemaRef(
        $testSend['responses']['200']['content']['application/json']['schema']['properties']['status'] ?? [],
        'AlertNotificationStatus',
    );

    expect($spec['components']['schemas']['StoreSegmentRequest']['properties'] ?? null)->toHaveKeys(['name', 'sentence', 'conditions', 'dimensions', 'kind', 'feeds']);
    expect($spec['components']['schemas']['UpdateSegmentRequest']['properties'] ?? null)->toHaveKeys(['name', 'sentence', 'conditions', 'dimensions', 'kind', 'feeds', 'active']);
    expect($spec['components']['schemas']['UpdateAutomationRequest']['properties'] ?? null)->toHaveKeys(['enabled', 'reason']);
    expect($spec['components']['schemas']['UpdateJourneyRequest']['properties'] ?? null)->toHaveKey('active');
    expect($spec['components']['schemas']['StoreTemplateDraftRequest']['properties'] ?? null)->toHaveKeys(['subject', 'body']);
    expect($spec['components']['schemas']['PublishTemplateVersionRequest']['properties'] ?? null)->toHaveKey('approval_reference');
    expect($spec['components']['schemas']['PreviewTemplateRequest']['properties'] ?? null)->toHaveKeys(['contact_id', 'booking_id', 'version']);

    $conversation = crmOpenApiSchema($spec, 'CrmConversationResource');
    crmSchemaRef($conversation['properties']['status'] ?? [], 'ConversationStatus');
    crmSchemaRef($conversation['properties']['messages']['items'] ?? [], 'CrmMessageResource');

    $message = crmOpenApiSchema($spec, 'CrmMessageResource');
    crmSchemaRef($message['properties']['direction'] ?? [], 'MessageDirection');
    expect($message['properties']['body_html']['type'] ?? null)->toBe('string');
    expect($message['properties']['body_text']['type'] ?? null)->toBe('string');
    expect($message['properties']['to']['items']['type'] ?? null)->toBe('string');

    $partner = crmOpenApiSchema($spec, 'B2bPartnerResource');
    expect($partner['properties'])->toHaveKeys(['contact', 'enrolment', 'deals', 'status', 'revenue', 'commission_accrued']);
    crmSchemaRef($partner['properties']['status'] ?? [], 'AgencyStatus');
    expect($partner['properties']['contact']['properties'] ?? null)->toHaveKeys(['id', 'name']);
    crmSchemaRef($partner['properties']['deals']['items'] ?? [], 'DealResource');

    $enrolmentArms = array_values(array_filter(
        $partner['properties']['enrolment']['anyOf'] ?? [],
        fn (mixed $arm): bool => is_array($arm) && isset($arm['properties']),
    ));
    expect($enrolmentArms)->toHaveCount(2);

    foreach ($enrolmentArms as $arm) {
        crmSchemaRef($arm['properties']['status'] ?? [], 'JourneyEnrolmentStatus');
        expect($arm['properties']['step']['properties'] ?? null)->toHaveKey('name');
    }

    $fullEnrolment = collect($enrolmentArms)->first(
        fn (array $arm): bool => isset($arm['properties']['sends']),
    );
    expect($fullEnrolment['properties']['sends']['items']['properties'] ?? null)->toHaveKeys([
        'sent_at',
        'template_key',
        'catalogue_key',
        'delivery_id',
    ]);

    $partnerArms = $spec['components']['schemas']['B2bPartnerResource']['anyOf'] ?? [];
    $partnerKeys = array_map(
        fn (mixed $arm): array => is_array($arm) ? array_keys($arm['properties'] ?? []) : [],
        $partnerArms,
    );
    expect(collect($partnerKeys)->contains(fn (array $keys): bool => in_array('deals', $keys, true)))->toBeTrue();
    expect(collect($partnerKeys)->contains(fn (array $keys): bool => ! in_array('deals', $keys, true)))->toBeTrue();

    expect($spec['components']['schemas']['ReplyToConversationRequest']['properties'] ?? null)->toHaveKey('message');
    expect($spec['components']['schemas']['LinkConversationContactRequest']['properties'] ?? null)->toHaveKey('contact_id');
});
