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
        'BookingFormOptionsResource',
        'GroupResource',
        'ContactResource',
        'MovePreviewResource',
        'BookingAuditResource',
        'BookingRequestResource',
        'HoldResource',
        'WaitlistEntryResource',
        'PaymentResource',
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
    expect($booking['properties'])->toHaveKeys(['allowed_transitions', 'request', 'paid', 'pledged', 'payments_count']);

    $payment = openApiSchema($spec, 'PaymentResource');
    expect($payment['properties'])->toHaveKeys([
        'reference',
        'date',
        'kind',
        'method',
        'amount',
        'status',
        'gateway_id',
        'recorded_by',
        'can_mark_wire',
        'booking',
    ]);

    $ledger = $spec['paths']['/rms/payments']['get']
        ?? $spec['paths']['/api/rms/payments']['get']
        ?? null;
    expect($ledger)->toBeArray();
    $bookingDeparture = $booking['properties']['departure']['properties'] ?? [];
    expect($bookingDeparture)->toHaveKeys(['itinerary_name', 'return_date', 'embark', 'festive']);

    $owners = $spec['paths']['/rms/bookings/owners']['get']
        ?? $spec['paths']['/api/rms/bookings/owners']['get']
        ?? null;
    expect($owners)->toBeArray();

    $groups = $spec['paths']['/rms/groups']['get']
        ?? $spec['paths']['/api/rms/groups']['get']
        ?? null;
    expect($groups)->toBeArray();
    $groupParams = collect($groups['parameters'] ?? [])
        ->mapWithKeys(fn (array $parameter): array => [($parameter['name'] ?? '') => $parameter]);
    expect($groupParams->keys()->all())->toContain('from', 'to');

    $transitionItems = $booking['properties']['allowed_transitions']['items'] ?? [];
    $transitionItemProps = is_array($transitionItems)
        ? ($transitionItems['properties'] ?? $transitionItems['anyOf'][0]['properties'] ?? [])
        : [];
    if ($transitionItemProps !== []) {
        expect($transitionItemProps)->toHaveKeys(['to', 'reason_required']);
    } else {
        expect($transitionItems)->toBeArray();
    }

    $quote = openApiSchema($spec, 'ReservationQuoteResource');
    expect($quote['properties'])->toHaveKey('terms');
    $terms = openApiProperties($quote['properties']['terms'] ?? []);
    expect($terms)->toHaveKeys(['balance_days', 'charter']);

    $formOptions = $spec['paths']['/rms/bookings/form-options']['get']
        ?? $spec['paths']['/api/rms/bookings/form-options']['get']
        ?? null;
    expect($formOptions)->toBeArray();

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

    $hold = openApiSchema($spec, 'HoldResource');
    expect($hold['properties'])->toHaveKeys(['booking_id', 'departure', 'remaining_business_minutes']);
    $holdDeparture = $hold['properties']['departure'] ?? [];
    expect($holdDeparture['type'] ?? $holdDeparture['properties'] ?? null)->not->toBe('string');
    expect($holdDeparture['properties'] ?? [])->toHaveKeys(['date', 'yacht']);
    expect($hold['properties']['remaining_business_minutes']['type'] ?? null)->toBe('integer');
    $holdRequest = $booking['properties']['request']['properties']['hold']['properties']
        ?? $booking['properties']['request']['properties']['hold']
        ?? [];
    $holdRequestProps = $holdRequest['properties'] ?? $holdRequest;
    expect($holdRequestProps)->toHaveKey('remaining_business_minutes');

    $holds = $spec['paths']['/rms/holds']['get']
        ?? $spec['paths']['/api/rms/holds']['get']
        ?? null;
    expect($holds)->toBeArray();
    $holdParams = collect($holds['parameters'] ?? [])
        ->mapWithKeys(fn (array $parameter): array => [($parameter['name'] ?? '') => $parameter]);
    expect($holdParams->keys()->all())->toContain('from', 'to');
    $holdsSchema = $holds['responses']['200']['content']['application/json']['schema'] ?? [];
    $holdsRules = $holdsSchema['properties']['meta']['properties']['rules']['properties']
        ?? $holdsSchema['properties']['meta']['properties']['rules']
        ?? null;
    expect($holdsRules)->toBeArray();
    $holdsRuleFields = $holdsRules['properties'] ?? $holdsRules;
    expect($holdsRuleFields)->toHaveKey('business_day_minutes');

    foreach ([
        'BookingStatus',
        'BookingType',
        'BookingSegment',
        'MainChannel',
        'ChannelOfOrigin',
        'PaymentKind',
        'PaymentMethod',
        'PaymentStatus',
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
