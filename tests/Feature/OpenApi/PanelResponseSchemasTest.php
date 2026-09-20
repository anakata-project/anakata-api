<?php

declare(strict_types=1);

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Illuminate\Support\Facades\Gate;

/**
 * @param  array<string, mixed>  $spec
 * @return array<string, mixed>
 */
function openApiSchema(array $spec, string $name): array
{
    $schema = $spec['components']['schemas'][$name] ?? null;

    expect($schema)->toBeArray("schema {$name} is missing");
    expect($schema['properties'] ?? null)->toBeArray("schema {$name} has no properties");
    expect($schema['properties'])->not->toBeEmpty("schema {$name} properties are empty");

    return $schema;
}

/**
 * @param  array<string, mixed>  $node
 * @return array<string, mixed>
 */
function openApiProperties(array $node): array
{
    if (isset($node['$ref']) && is_string($node['$ref'])) {
        return [];
    }

    $properties = $node['properties'] ?? null;

    expect($properties)->toBeArray();
    expect($properties)->not->toBeEmpty();

    return $properties;
}

test('panel-read OpenAPI schemas have properties', function (): void {
    Gate::define('viewApiDocs', fn (): bool => true);

    $response = $this->withoutMiddleware(RestrictedDocsAccess::class)
        ->getJson('/docs/api.json')
        ->assertOk();

    /** @var array<string, mixed> $spec */
    $spec = $response->json();

    foreach ([
        'DepartureResource',
        'InternalBlockResource',
        'CalendarGridResource',
        'GenerateSeasonResource',
        'DepartureMutationResource',
        'BookingResource',
        'ReservationQuoteResource',
        'ReservationCreatedResource',
        'GroupResource',
        'ContactResource',
        'MovePreviewResource',
        'BookingAuditResource',
        'BookingRequestResource',
        'HoldResource',
        'WaitlistEntryResource',
    ] as $name) {
        openApiSchema($spec, $name);
    }

    $departure = openApiSchema($spec, 'DepartureResource');
    $availability = openApiProperties($departure['properties']['availability'] ?? []);
    $cabins = $availability['cabins']['items'] ?? $availability['cabins'] ?? null;
    expect($cabins)->toBeArray();

    $cabinItem = $cabins['items'] ?? $cabins;
    $claim = $cabinItem['properties']['claim'] ?? $cabinItem['anyOf'][0]['properties']['claim'] ?? null;

    if (isset($cabinItem['properties']['claim'])) {
        $claim = $cabinItem['properties']['claim'];
    } elseif (isset($cabinItem['anyOf'])) {
        foreach ($cabinItem['anyOf'] as $option) {
            if (isset($option['properties']['claim'])) {
                $claim = $option['properties']['claim'];
                break;
            }
        }
    }

    expect($claim)->toBeArray();

    $claimProps = $claim['properties'] ?? $claim['anyOf'][0]['properties'] ?? [];
    $holder = $claimProps['holder']['properties'] ?? [];
    expect($holder)->toHaveKey('detail');

    $index = $spec['paths']['/rms/departures']['get']
        ?? $spec['paths']['/api/rms/departures']['get']
        ?? null;
    expect($index)->toBeArray();

    $indexSchema = $index['responses']['200']['content']['application/json']['schema'] ?? [];
    $kpis = $indexSchema['properties']['meta']['properties']['kpis']['properties']
        ?? $indexSchema['properties']['meta']['properties']['kpis']
        ?? null;
    expect($kpis)->toBeArray();
    $kpiFields = $kpis['properties'] ?? $kpis;
    expect($kpiFields)->toHaveKeys([
        'on_sale_on_engine',
        'cabins_bookable',
        'showing_only_n_left',
        'full',
    ]);

    $unavailable = $spec['components']['responses']['CabinUnavailableException'] ?? null;
    expect($unavailable)->toBeArray();
    $body = $unavailable['content']['application/json']['schema']['properties'] ?? [];
    expect($body)->toHaveKey('unavailable');
    $item = $body['unavailable']['items']['properties'] ?? [];
    expect($item)->toHaveKeys(['cabin', 'held_by']);

    $blockStore = $spec['paths']['/rms/blocks']['post']
        ?? $spec['paths']['/api/rms/blocks']['post']
        ?? null;
    expect($blockStore)->toBeArray();
    $conflict = $blockStore['responses']['409'] ?? null;
    expect($conflict)->toBeArray();
    $conflictRef = $conflict['$ref'] ?? $conflict['content']['application/json']['schema']['$ref'] ?? null;
    expect($conflictRef)->toBeString();
    expect($conflictRef)->toContain('CabinUnavailableException');

    $booking = openApiSchema($spec, 'BookingResource');
    expect($booking['properties'])->toHaveKey('allowed_transitions');

    $transitionItems = $booking['properties']['allowed_transitions']['items'] ?? [];
    $transitionItemProps = is_array($transitionItems)
        ? ($transitionItems['properties'] ?? $transitionItems['anyOf'][0]['properties'] ?? [])
        : [];
    if ($transitionItemProps !== []) {
        expect($transitionItemProps)->toHaveKeys(['to', 'reason_required']);
    } else {
        expect($transitionItems)->toBeArray();
    }

    $created = openApiSchema($spec, 'ReservationCreatedResource');
    expect($created['properties'])->toHaveKey('bookings');

    $store = $spec['paths']['/rms/bookings']['post']
        ?? $spec['paths']['/api/rms/bookings']['post']
        ?? null;
    expect($store)->toBeArray();
    $storeBookings = $store['responses']['201']['content']['application/json']['schema']['properties']['bookings']['items']
        ?? $created['properties']['bookings']['items']
        ?? null;
    expect($storeBookings)->toBeArray();
    $storeBookingRef = $storeBookings['$ref'] ?? null;
    $storeBookingProps = $storeBookings['properties'] ?? [];
    if (is_string($storeBookingRef)) {
        expect($storeBookingRef)->toContain('BookingResource');
    } else {
        expect($storeBookingProps)->toHaveKeys(['id', 'status', 'type']);
    }

    $requests = $spec['paths']['/rms/requests']['get']
        ?? $spec['paths']['/api/rms/requests']['get']
        ?? null;
    expect($requests)->toBeArray();
    $requestSchema = $requests['responses']['200']['content']['application/json']['schema'] ?? [];
    $rules = $requestSchema['properties']['meta']['properties']['rules']['properties']
        ?? $requestSchema['properties']['meta']['properties']['rules']
        ?? null;
    expect($rules)->toBeArray();
    $ruleFields = $rules['properties'] ?? $rules;
    expect($ruleFields)->toHaveKeys([
        'near_term_business_hours',
        'long_lead_business_days',
        'near_term_max_days',
        'response_hours',
        'business_day_minutes',
        'cabin_deposit_pct',
    ]);

    foreach ([
        'BookingStatus',
        'BookingType',
        'BookingSegment',
        'MainChannel',
        'ChannelOfOrigin',
    ] as $enum) {
        $schema = $spec['components']['schemas'][$enum] ?? null;
        expect($schema)->toBeArray("schema {$enum} is missing");
        expect($schema['enum'] ?? $schema['oneOf'] ?? $schema['anyOf'] ?? null)
            ->not->toBeNull("schema {$enum} is not an enum");
    }

    $transition = $spec['paths']['/rms/bookings/{booking}/transition']['post']
        ?? $spec['paths']['/api/rms/bookings/{booking}/transition']['post']
        ?? null;
    expect($transition)->toBeArray();
    expect($transition['responses']['409'] ?? null)->toBeArray();

    $move = $spec['paths']['/rms/bookings/{booking}/move']['post']
        ?? $spec['paths']['/api/rms/bookings/{booking}/move']['post']
        ?? null;
    expect($move)->toBeArray();
    expect($move['responses']['409'] ?? null)->toBeArray();
});
