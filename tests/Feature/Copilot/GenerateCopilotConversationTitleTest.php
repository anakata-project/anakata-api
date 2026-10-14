<?php

declare(strict_types=1);

use App\Ai\Agents\CopilotTitleAgent;
use App\Events\CopilotConversationTitled;
use App\Jobs\GenerateCopilotConversationTitle;
use App\Models\CopilotConversation;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Laravel\Ai\Attributes\UseCheapestModel;
use ReflectionClass;
use ReflectionMethod;

it('saves a generated title and broadcasts it', function (): void {
    $manager = managerUser();
    $conversation = untitledConversation($manager);

    Event::fake([CopilotConversationTitled::class]);
    CopilotTitleAgent::fake(['Recent contacts']);

    (new GenerateCopilotConversationTitle($conversation->id, 'Show me my recent contacts'))->handle();

    expect($conversation->fresh()?->title)->toBe('Recent contacts');

    Event::assertDispatched(CopilotConversationTitled::class, function (CopilotConversationTitled $event) use ($conversation): bool {
        return $event->conversationId === $conversation->id
            && $event->title === 'Recent contacts'
            && $event->broadcastAs() === 'copilot.conversation.titled'
            && $event->broadcastOn()[0]->name === 'private-copilot.'.$conversation->id
            && $event->broadcastWith() === [
                'conversation_id' => $conversation->id,
                'title' => 'Recent contacts',
            ];
    });
});

it('falls back to the message when the title model fails', function (): void {
    $manager = managerUser();
    $message = 'Show me every contact created in the last thirty days';
    $conversation = untitledConversation($manager);

    Event::fake([CopilotConversationTitled::class]);
    CopilotTitleAgent::fake(fn () => throw new RuntimeException('provider down'));

    (new GenerateCopilotConversationTitle($conversation->id, $message))->handle();

    expect($conversation->fresh()?->title)->toBe(Str::limit($message, 50, '...'));
    Event::assertDispatched(CopilotConversationTitled::class);
});

it('leaves a titled conversation alone', function (): void {
    $manager = managerUser();
    $conversation = untitledConversation($manager, 'Already named');

    Event::fake([CopilotConversationTitled::class]);
    CopilotTitleAgent::fake(['Should not run'])->preventStrayPrompts();

    (new GenerateCopilotConversationTitle($conversation->id, 'Show me my recent contacts'))->handle();

    expect($conversation->fresh()?->title)->toBe('Already named');
    Event::assertNotDispatched(CopilotConversationTitled::class);
});

it('uses the cheapest text model', function (): void {
    $agent = new CopilotTitleAgent;
    $attributes = (new ReflectionClass($agent))->getAttributes(UseCheapestModel::class);
    $provider = Ai::textProviderFor($agent, 'anthropic');
    $method = new ReflectionMethod($agent, 'getDefaultModelFor');

    expect($attributes)->not->toBeEmpty()
        ->and($method->invoke($agent, $provider))->toBe($provider->cheapestTextModel())
        ->and($provider->cheapestTextModel())->toBe('claude-haiku-4-5-20251001');
});

function untitledConversation(User $user, string $title = CopilotConversation::UNTITLED): CopilotConversation
{
    return CopilotConversation::query()->create([
        'id' => (string) Str::uuid7(),
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
        'title' => $title,
    ]);
}
