<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Contacts\ResolveContact;
use App\Ai\Tools\Concerns\ReturnsToolJson;
use App\Enums\ContactType;
use App\Enums\Permission;
use App\Enums\PreferredChannel;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Context;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class CreateContact implements Approvable, Tool
{
    use InteractsWithApprovals;
    use ReturnsToolJson;

    public function __construct(private readonly User $user) {}

    public function description(): Stringable|string
    {
        return 'Create a CRM contact, or return the existing contact when the email already exists.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->user->hasPermission(Permission::ContactsManage)) {
            return $this->failure('You do not have permission to create contacts.');
        }

        Context::add('history_source', 'copilot');

        try {
            $contact = app(ResolveContact::class)->handle([
                'name' => trim((string) $request['name']),
                'email' => $request['email'] ?? null,
                'phone' => $request['phone'] ?? null,
                'country' => $request['country'] ?? null,
                'preferred_channel' => $request['preferred_channel'] ?? null,
                'type' => $request['type'] ?? null,
                'language' => $request['language'] ?? null,
            ]);
        } catch (Throwable $exception) {
            return $this->fromException($exception);
        }

        return $this->success([
            'message' => "Contact '{$contact->name}' is ready.",
            'contact_id' => $contact->id,
            'contact_name' => $contact->name,
            'email' => $contact->email,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Full name')->required(),
            'email' => $schema->string()->description('Email address')->nullable(),
            'phone' => $schema->string()->description('Phone number')->nullable(),
            'country' => $schema->string()->description('ISO country code')->nullable(),
            'preferred_channel' => $schema->string()
                ->description('Preferred channel')
                ->enum(array_map(fn (PreferredChannel $channel): string => $channel->value, PreferredChannel::cases()))
                ->nullable(),
            'type' => $schema->string()
                ->description('Contact type')
                ->enum(array_map(fn (ContactType $type): string => $type->value, ContactType::cases()))
                ->nullable(),
            'language' => $schema->string()->description('Language code, usually en')->nullable(),
        ];
    }
}
