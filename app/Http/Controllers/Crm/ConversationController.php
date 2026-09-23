<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Actions\Crm\LinkConversationContact;
use App\Actions\Crm\MarkConversationRead;
use App\Actions\Crm\SendConversationReply;
use App\Actions\Crm\UpdateConversationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\IndexConversationsRequest;
use App\Http\Requests\Crm\LinkConversationContactRequest;
use App\Http\Requests\Crm\ReplyToConversationRequest;
use App\Http\Requests\Crm\UpdateConversationRequest;
use App\Http\Resources\Crm\CrmConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ConversationController extends Controller
{
    #[DocumentedResponse(
        status: 200,
        type: 'array{data: list<App\\Http\\Resources\\Crm\\CrmConversationResource>, links: array{first: string|null, last: string|null, prev: string|null, next: string|null}, meta: array{current_page: int, from: int|null, last_page: int, path: string|null, per_page: int, to: int|null, total: int}}',
    )]
    public function index(IndexConversationsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Conversation::class);

        $conversations = Conversation::query()
            ->with(['contact', 'latestMessage', 'latestInbound'])
            ->withCount('messages')
            ->when($request->status() !== null, fn ($query) => $query->where('status', $request->status()))
            ->when($request->unread() !== null, fn ($query) => $query->where('unread', $request->unread()))
            ->when($request->contactId() !== null, fn ($query) => $query->where('contact_id', $request->contactId()))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($request->perPage());

        return CrmConversationResource::collection($conversations);
    }

    #[DocumentedResponse(status: 200, type: 'App\\Http\\Resources\\Crm\\CrmConversationResource')]
    public function show(Conversation $conversation, MarkConversationRead $read): CrmConversationResource
    {
        $this->authorize('view', $conversation);

        return $this->present($read->handle($conversation, $this->actor()));
    }

    #[DocumentedResponse(status: 200, type: 'App\\Http\\Resources\\Crm\\CrmConversationResource')]
    public function reply(
        ReplyToConversationRequest $request,
        Conversation $conversation,
        SendConversationReply $replies,
    ): CrmConversationResource {
        $this->authorize('reply', $conversation);
        $replies->handle($conversation, $request->string('message')->toString(), $this->actor());

        return $this->present($conversation->refresh());
    }

    #[DocumentedResponse(status: 200, type: 'App\\Http\\Resources\\Crm\\CrmConversationResource')]
    public function update(
        UpdateConversationRequest $request,
        Conversation $conversation,
        UpdateConversationStatus $update,
    ): CrmConversationResource {
        $this->authorize('update', $conversation);

        return $this->present($update->handle($conversation, $request->status(), $this->actor()));
    }

    #[DocumentedResponse(status: 200, type: 'App\\Http\\Resources\\Crm\\CrmConversationResource')]
    public function linkContact(
        LinkConversationContactRequest $request,
        Conversation $conversation,
        LinkConversationContact $link,
    ): CrmConversationResource {
        $this->authorize('link', $conversation);

        return $this->present($link->handle($conversation, $request->contact(), $this->actor()));
    }

    private function present(Conversation $conversation): CrmConversationResource
    {
        $conversation->load([
            'contact',
            'latestMessage',
            'latestInbound',
            'messages' => fn ($query) => $query->orderBy('sent_at')->orderBy('id'),
        ]);
        $conversation->loadCount('messages');

        return new CrmConversationResource($conversation);
    }

    private function actor(): User
    {
        $actor = request()->user();

        if (! $actor instanceof User) {
            throw new HttpException(403, 'You cannot change this conversation.');
        }

        return $actor;
    }
}
