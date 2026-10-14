<?php

declare(strict_types=1);

use App\Ai\Agents\AnakataCopilotAgent;
use App\Ai\Agents\CopilotTitleAgent;
use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\CopilotConversation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

it('lets a manager list, open and archive their own conversations', function (): void {
    $manager = managerUser();

    $created = $this->actingAs($manager)
        ->postJson('/api/copilot/conversations')
        ->assertCreated()
        ->assertJsonPath('data.title', CopilotConversation::UNTITLED);

    $id = $created->json('data.id');

    $this->actingAs($manager)
        ->getJson('/api/copilot/conversations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $id);

    $this->actingAs($manager)
        ->getJson("/api/copilot/conversations/{$id}")
        ->assertOk()
        ->assertJsonPath('data', []);

    $this->actingAs($manager)
        ->deleteJson("/api/copilot/conversations/{$id}")
        ->assertOk();

    $this->actingAs($manager)
        ->getJson("/api/copilot/conversations/{$id}")
        ->assertNotFound();
});

it('refuses the copilot to a role without the permission', function (): void {
    $role = Role::factory()->create([
        'permissions' => [Permission::PanelRms->value],
    ]);
    $user = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($user)
        ->getJson('/api/copilot/conversations')
        ->assertForbidden();
});

it('hides another staff member\'s conversation', function (): void {
    $owner = managerUser();
    $other = salesExecUser();

    $conversation = CopilotConversation::query()->create([
        'id' => (string) Str::uuid7(),
        'participant_type' => $owner->getMorphClass(),
        'participant_id' => $owner->id,
        'title' => 'Private',
    ]);

    $this->actingAs($other)
        ->getJson("/api/copilot/conversations/{$conversation->id}")
        ->assertForbidden();

    $this->actingAs($other)
        ->getJson('/api/copilot/conversations')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('queues a turn and titles the conversation with the cheap model', function (): void {
    AnakataCopilotAgent::fake();
    CopilotTitleAgent::fake(['Sunday bookings']);
    $manager = managerUser();
    $clientMessageId = (string) Str::uuid();

    $conversation = $this->actingAs($manager)
        ->postJson('/api/copilot/conversations')
        ->json('data.id');

    $first = $this->actingAs($manager)
        ->postJson("/api/copilot/conversations/{$conversation}/messages", [
            'message' => 'Who is booked on the next Sunday?',
            'client_message_id' => $clientMessageId,
        ])
        ->assertAccepted()
        ->assertJsonPath('conversation_id', $conversation)
        ->assertJsonPath('channel', "private-copilot.{$conversation}");

    $this->assertDatabaseHas('copilot_conversations', [
        'id' => $conversation,
        'title' => 'Sunday bookings',
    ]);
    CopilotTitleAgent::assertPrompted('Who is booked on the next Sunday?');

    $second = $this->actingAs($manager)
        ->postJson("/api/copilot/conversations/{$conversation}/messages", [
            'message' => 'Who is booked on the next Sunday?',
            'client_message_id' => $clientMessageId,
        ])
        ->assertAccepted();

    expect($second->json('call_id'))->toBe($first->json('call_id'));
    AnakataCopilotAgent::assertQueued('Who is booked on the next Sunday?');
});

it('rejects a message without a client id', function (): void {
    $manager = managerUser();
    $conversation = CopilotConversation::query()->create([
        'id' => (string) Str::uuid7(),
        'participant_type' => $manager->getMorphClass(),
        'participant_id' => $manager->id,
        'title' => CopilotConversation::UNTITLED,
    ]);

    $this->actingAs($manager)
        ->postJson("/api/copilot/conversations/{$conversation->id}/messages", [
            'message' => 'Hello',
        ])
        ->assertUnprocessable();
});

it('grants the copilot to the seeded manager and sales exec roles', function (): void {
    expect(SystemRole::Manager->defaultPermissions()->contains(Permission::CopilotUse))->toBeTrue()
        ->and(SystemRole::SalesExec->defaultPermissions()->contains(Permission::CopilotUse))->toBeTrue()
        ->and(adminUser()->hasPermission(Permission::CopilotUse))->toBeTrue();
});
