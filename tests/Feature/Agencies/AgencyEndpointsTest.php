<?php

declare(strict_types=1);

use App\Enums\AgencyStatus;
use App\Enums\AgencyUserStatus;
use App\Enums\MainChannel;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\ChangeHistory;
use App\Services\Config\CurrentConfig;
use App\Support\Rounding;
use Carbon\CarbonImmutable;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\RolesSeeder;
use Tests\Support\Bookings\ReservationFixtures;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(InventorySeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('a manager can register an agency and a sales exec cannot', function (): void {
    $this->actingAs(salesExecUser())
        ->postJson('/api/rms/agencies', [
            'name' => 'Andes Luxe',
            'contact' => 'P. Ibanez',
            'email' => 'p.ibanez@andes.test',
        ])
        ->assertForbidden();

    $this->actingAs(managerUser())
        ->postJson('/api/rms/agencies', [
            'name' => 'Andes Luxe',
            'contact' => 'P. Ibanez',
            'email' => 'p.ibanez@andes.test',
            'country' => 'CL',
            'network' => 'Signature',
        ])
        ->assertCreated()
        ->assertJsonPath('status', AgencyStatus::Pending->value)
        ->assertJsonPath('commission_pct', 10)
        ->assertJsonPath('users.0.status', AgencyUserStatus::Pending->value)
        ->assertJsonPath('sla_breached', false);

    expect(ChangeHistory::query()->where('event', 'agency.registered')->count())->toBe(1);
});

test('agency sla is breached at three business days and two is the boundary', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-23 12:00:00', 'Pacific/Galapagos'));

    $three = Agency::factory()->pending()->create([
        'name' => 'Three days',
        'requested_at' => CarbonImmutable::parse('2026-09-18 10:00:00', 'Pacific/Galapagos'),
    ]);
    $two = Agency::factory()->pending()->create([
        'name' => 'Two days',
        'requested_at' => CarbonImmutable::parse('2026-09-21 10:00:00', 'Pacific/Galapagos'),
    ]);

    $rows = $this->actingAs(managerUser())
        ->getJson('/api/rms/agencies')
        ->assertOk()
        ->json('data');

    $byId = collect($rows)->keyBy('id');
    expect($byId[$three->id]['sla_business_days_elapsed'])->toBe(3);
    expect($byId[$three->id]['sla_breached'])->toBeTrue();
    expect($byId[$two->id]['sla_business_days_elapsed'])->toBe(2);
    expect($byId[$two->id]['sla_breached'])->toBeTrue();
});

test('approval invites users and rejection requires a reason', function (): void {
    $agency = Agency::factory()->pending()->create();
    $agency->users()->create([
        'name' => $agency->contact,
        'email' => $agency->email,
        'status' => AgencyUserStatus::Pending,
    ]);

    $this->actingAs(managerUser())
        ->postJson('/api/rms/agencies/'.$agency->id.'/decide', [
            'decision' => AgencyStatus::Rejected->value,
        ])
        ->assertUnprocessable();

    $this->actingAs(managerUser())
        ->postJson('/api/rms/agencies/'.$agency->id.'/decide', [
            'decision' => AgencyStatus::Approved->value,
        ])
        ->assertOk()
        ->assertJsonPath('status', AgencyStatus::Approved->value)
        ->assertJsonPath('users.0.status', AgencyUserStatus::Invited->value);

    expect(ChangeHistory::query()->where('event', 'agency.approved')->count())->toBe(1);
});

test('patching the agency rate does not change a sold booking', function (): void {
    $agency = Agency::factory()->create(['commission_pct' => 10]);
    $departure = ReservationFixtures::anamaraDeparture();

    $id = $this->actingAs(managerUser())
        ->postJson('/api/rms/bookings', ReservationFixtures::createPayload($departure, [
            'main_channel' => MainChannel::B2BTravelAdvisor->value,
            'channel_of_origin' => 'Travel Advisor',
            'agency_id' => $agency->id,
        ]))
        ->assertCreated()
        ->json('bookings.0.id');

    $this->actingAs(managerUser())
        ->patchJson('/api/rms/agencies/'.$agency->id, ['commission_pct' => 15])
        ->assertOk()
        ->assertJsonPath('commission_pct', 15);

    expect(Booking::query()->findOrFail($id)->commission_pct)->toBe(10);
});

test('agency show returns net rates and no public suite owner or charter prices', function (): void {
    $agency = Agency::factory()->create(['commission_pct' => 10]);
    $rates = app(CurrentConfig::class)->rates();

    $response = $this->actingAs(managerUser())
        ->getJson('/api/rms/agencies/'.$agency->id)
        ->assertOk()
        ->assertJsonPath('portal_preview.commission_pct', 10);

    $json = $response->json();
    $encoded = json_encode($json);
    expect($encoded)->not->toContain('"suite_pp":'.$rates->years[0]->suitePp);
    expect($encoded)->not->toContain('"owner_pp":'.$rates->years[0]->ownerPp);
    expect($encoded)->not->toContain('"charter_week":'.$rates->years[0]->charterWeek);

    $net = $json['portal_preview']['net_rates'][0];
    expect($net['suite_pp'])->toBe(Rounding::halfUp($rates->years[0]->suitePp * 0.9));
    expect($net['owner_pp'])->toBe(Rounding::halfUp($rates->years[0]->ownerPp * 0.9));
    expect($net['charter_week'])->toBe(Rounding::halfUp($rates->years[0]->charterWeek * 0.9));
});

test('form options list only approved agencies', function (): void {
    $approved = Agency::factory()->create(['name' => 'Blue Latitude']);
    Agency::factory()->pending()->create(['name' => 'Andes Pending']);

    $this->actingAs(salesExecUser())
        ->getJson('/api/rms/bookings/form-options')
        ->assertOk()
        ->assertJsonPath('agencies.0.id', $approved->id)
        ->assertJsonCount(1, 'agencies');
});
