<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Middleware\ActAsCopilotUser;
use App\Ai\Tools\CreateContact;
use App\Ai\Tools\CreateDeal;
use App\Ai\Tools\CreateTask;
use App\Ai\Tools\FetchObjects;
use App\Ai\Tools\LogContactActivity;
use App\Ai\Tools\MoveDealStage;
use App\Ai\Tools\ResolveCalendar;
use App\Ai\Tools\UpdateContact;
use App\Models\User;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Attributes\WithoutBroadcasting;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Stringable;

#[Timeout(120)]
#[WithoutBroadcasting(ToolCall::class, ToolResult::class)]
class AnakataCopilotAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable;
    use RemembersConversations;

    public function __construct(public User $user) {}

    /**
     * @return array<int, class-string>
     */
    public function middleware(): array
    {
        return [
            ActAsCopilotUser::class,
        ];
    }

    public function provider(): string
    {
        return 'anthropic';
    }

    public function model(): ?string
    {
        $model = config('ai.copilot.model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are the staff copilot for Anakata, a Galápagos yacht expedition company. The yachts are ANAMARA and ANATIVA. Cruises are 7 nights, Sunday to Sunday. Money is whole US dollars.

You help the signed-in staff member with the RMS (reservations) and the CRM (contacts, deals, tasks). You are not the guest booking engine.

You can read, when the user's role allows it: contacts, deals, tasks, bookings, departures (with cabin counts), offers, payments, charter enquiries, and agencies. Use FetchObjects and its filters. Do not invent records.

You can create or change only these CRM things, and nothing else: a contact, a contact's details, a manual task, a contact activity (call, email, meeting, note), an unbound deal, and an unbound deal's stage. You cannot take payments, refunds, commissions, or cancel or change a booking. If asked, say so.

Every write tool needs the operator's approval and their own permission. When you intend to create or change something, call the tool directly. Do not ask a yes/no question in chat first. The approval card confirms it. If a tool result says the user lacks permission, or that a deal follows a booking, tell them plainly and do not retry.

Relative dates go through ResolveCalendar. Pass the operator's words. Never calculate a date yourself.

Search before creating a contact or a deal. Be concise. Refer to bookings and departures by reference, contacts by name.
PROMPT;
    }

    /**
     * @return iterable<int, mixed>
     */
    public function tools(): iterable
    {
        return [
            (new CreateContact($this->user))->requireApproval('Create a contact'),
            (new UpdateContact($this->user))->requireApproval('Update a contact'),
            (new CreateTask($this->user))->requireApproval('Create a task'),
            (new LogContactActivity($this->user))->requireApproval('Log an activity'),
            (new CreateDeal($this->user))->requireApproval('Create a deal'),
            (new MoveDealStage($this->user))->requireApproval('Move a deal'),
            new FetchObjects($this->user),
            new ResolveCalendar,
        ];
    }
}
