<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\Contacts\UpdateContact as UpdateContactAction;
use App\Ai\Tools\Concerns\ReturnsToolJson;
use App\Enums\ContactType;
use App\Enums\PreferredChannel;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class UpdateContact implements Approvable, Tool
{
    use InteractsWithApprovals;
    use ReturnsToolJson;

    public function __construct(private readonly User $user) {}

    public function description(): Stringable|string
    {
        return 'Update a contact\'s name, email, phone, country, language, preferred channel, or type.';
    }

    public function handle(Request $request): Stringable|string
    {
        $contact = Contact::query()->find($request['contact_id'] ?? null);

        if (! $contact instanceof Contact) {
            return $this->failure('No contact found with that ID.');
        }

        if (! Gate::forUser($this->user)->allows('update', $contact)) {
            return $this->failure('You do not have permission to update this contact.');
        }

        $data = [];

        foreach (['name', 'email', 'phone', 'country', 'language', 'preferred_channel', 'type'] as $field) {
            if (array_key_exists($field, $request->all()) && $request[$field] !== null) {
                $data[$field] = $request[$field];
            }
        }

        if ($data === []) {
            return $this->failure('Provide at least one field to change.');
        }

        Context::add('history_source', 'copilot');

        try {
            $contact = app(UpdateContactAction::class)->handle($contact, $data, $this->user);
        } catch (Throwable $exception) {
            return $this->fromException($exception);
        }

        return $this->success([
            'message' => "Contact '{$contact->name}' updated.",
            'contact_id' => $contact->id,
            'contact_name' => $contact->name,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'contact_id' => $schema->integer()->description('Contact ID')->required(),
            'name' => $schema->string()->description('Full name')->nullable(),
            'email' => $schema->string()->description('Email address')->nullable(),
            'phone' => $schema->string()->description('Phone number')->nullable(),
            'country' => $schema->string()->description('ISO country code')->nullable(),
            'language' => $schema->string()->description('Language code')->nullable(),
            'preferred_channel' => $schema->string()
                ->enum(array_map(fn (PreferredChannel $channel): string => $channel->value, PreferredChannel::cases()))
                ->nullable(),
            'type' => $schema->string()
                ->enum(array_map(fn (ContactType $type): string => $type->value, ContactType::cases()))
                ->nullable(),
        ];
    }
}
