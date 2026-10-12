<?php

declare(strict_types=1);

use App\Actions\Crm\CreateUnboundDeal;
use App\Enums\DealStage;
use App\Enums\DealType;
use App\Models\Contact;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    $this->seed(ConfigSeeder::class);
});

test('the deals list is paginated and matches the board', function (): void {
    $owner = salesExecUser();
    $other = salesExecUser();
    $contact = Contact::factory()->create(['name' => 'Ada List']);

    app(CreateUnboundDeal::class)->handle(
        $contact,
        $owner,
        'Visible to everyone',
        DealType::Fit,
        DealStage::NewLead,
        12000,
        null,
    );
    app(CreateUnboundDeal::class)->handle(
        $contact,
        $other,
        'Quoted cabin',
        DealType::Group,
        DealStage::Quoted,
        40000,
        null,
    );

    $board = test()->actingAs($owner)->getJson('/api/crm/pipeline')->assertOk();
    $boardIds = collect($board->json('columns'))
        ->flatMap(fn (array $column): array => collect($column['deals'])->pluck('id')->all())
        ->sort()
        ->values();

    $list = test()->actingAs($owner)
        ->getJson('/api/crm/deals?per_page=1&page=1')
        ->assertOk();

    assertNoSensitiveFields($list);
    $list->assertJsonPath('meta.per_page', 1);
    $list->assertJsonPath('meta.total', 2);
    $list->assertJsonCount(1, 'data');

    $all = test()->actingAs($owner)->getJson('/api/crm/deals?per_page=50')->assertOk();
    $listIds = collect($all->json('data'))->pluck('id')->sort()->values();

    expect($listIds->all())->toBe($boardIds->all());
    expect(collect($all->json('data'))->pluck('title')->all())->toContain('Visible to everyone');
});

test('a sales exec sees a deal owned by someone else', function (): void {
    $owner = salesExecUser();
    $viewer = salesExecUser();
    $contact = Contact::factory()->create();

    $deal = app(CreateUnboundDeal::class)->handle(
        $contact,
        $owner,
        'Not mine',
        DealType::Charter,
        DealStage::Qualifying,
        8000,
        null,
    );

    test()->actingAs($viewer)
        ->getJson('/api/crm/deals')
        ->assertOk()
        ->assertJsonFragment(['id' => $deal->id, 'title' => 'Not mine']);
});

test('stage and search filters narrow the list', function (): void {
    $owner = salesExecUser();
    $contact = Contact::factory()->create(['name' => 'Bea Filter']);

    app(CreateUnboundDeal::class)->handle($contact, $owner, 'Keep me', DealType::Fit, DealStage::NewLead, 1000, null);
    app(CreateUnboundDeal::class)->handle($contact, $owner, 'Drop me', DealType::Fit, DealStage::Negotiation, 2000, null);

    test()->actingAs($owner)
        ->getJson('/api/crm/deals?stage=NEW_LEAD&q=Bea')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Keep me')
        ->assertJsonPath('data.0.stage', 'NEW_LEAD');
});

test('an unknown stage is rejected', function (): void {
    test()->actingAs(salesExecUser())
        ->getJson('/api/crm/deals?stage=NOT_A_STAGE')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['stage']);
});

test('deals stay behind panel.crm', function (): void {
    test()->actingAs(externalFinanceUser())
        ->getJson('/api/crm/deals')
        ->assertForbidden();
});
