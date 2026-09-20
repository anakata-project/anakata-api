<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Actions\Action;
use App\Enums\PreferredChannel;
use App\Models\Contact;
use App\Support\History\History;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ResolveContact extends Action
{
    /**
     * Created detection: INSERT affected rows === 1.
     * Laravel does not set PDO CLIENT_FOUND_ROWS / MYSQL_ATTR_FOUND_ROWS,
     * so a no-op `id = id` duplicate returns 0 (not 2).
     */
    public static function createdFromInsertAffected(int $affected): bool
    {
        return $affected === 1;
    }

    /**
     * @param  array{name: string, email?: string|null, phone?: string|null, country?: string|null, preferred_channel?: string|PreferredChannel|null}  $data
     */
    public function handle(array $data): Contact
    {
        return $this->transaction(fn (): Contact => $this->resolve($data));
    }

    /**
     * @param  array{name: string, email?: string|null, phone?: string|null, country?: string|null, preferred_channel?: string|PreferredChannel|null}  $data
     */
    private function resolve(array $data): Contact
    {
        $email = Contact::normalizeEmail(isset($data['email']) ? (string) $data['email'] : null);

        if ($email === null) {
            return $this->createWithoutEmail($data);
        }

        $now = now()->format('Y-m-d H:i:s');
        $actorId = Auth::id();
        $channel = $this->preferredChannel($data['preferred_channel'] ?? null);

        $affected = (int) DB::affectingStatement(
            'insert into contacts (`name`, `email`, `phone`, `country`, `preferred_channel`, `created_at`, `updated_at`, `created_by`, `updated_by`)
             values (?, ?, ?, ?, ?, ?, ?, ?, ?)
             on duplicate key update `id` = `id`',
            [
                $data['name'],
                $email,
                $this->nullableString($data['phone'] ?? null),
                $this->nullableCountry($data['country'] ?? null),
                $channel->value,
                $now,
                $now,
                $actorId,
                $actorId,
            ],
        );

        $contact = Contact::query()->where('email', $email)->first();

        if (! $contact instanceof Contact) {
            throw new RuntimeException('Contact upsert did not produce a row for '.$email.'.');
        }

        if (self::createdFromInsertAffected($affected)) {
            History::record($contact, 'contact.created', after: $this->snapshot($contact));

            return $contact;
        }

        return $this->fillEmptyFields($contact, $data, $channel);
    }

    /**
     * @param  array{name: string, email?: string|null, phone?: string|null, country?: string|null, preferred_channel?: string|PreferredChannel|null}  $data
     */
    private function createWithoutEmail(array $data): Contact
    {
        $contact = Contact::query()->create([
            'name' => $data['name'],
            'email' => null,
            'phone' => $this->nullableString($data['phone'] ?? null),
            'country' => $this->nullableCountry($data['country'] ?? null),
            'preferred_channel' => $this->preferredChannel($data['preferred_channel'] ?? null),
        ]);

        History::record($contact, 'contact.created', after: $this->snapshot($contact));

        return $contact;
    }

    /**
     * @param  array{name: string, email?: string|null, phone?: string|null, country?: string|null, preferred_channel?: string|PreferredChannel|null}  $data
     */
    private function fillEmptyFields(Contact $contact, array $data, PreferredChannel $channel): Contact
    {
        $incoming = [
            'name' => trim($data['name']),
            'phone' => $this->nullableString($data['phone'] ?? null),
            'country' => $this->nullableCountry($data['country'] ?? null),
            'preferred_channel' => $channel->value,
        ];

        $before = [];
        $after = [];
        $update = [];

        foreach ($incoming as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $current = $contact->getAttribute($field);

            if ($current instanceof PreferredChannel) {
                $current = $current->value;
            }

            if (is_string($current) && trim($current) !== '') {
                continue;
            }

            $before[$field] = $current === '' ? null : $current;
            $after[$field] = $value;
            $update[$field] = $value;
        }

        if ($update === []) {
            return $contact;
        }

        Contact::query()->whereKey($contact->id)->update([
            ...$update,
            'updated_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        $contact->refresh();

        History::record($contact, 'contact.updated', before: $before, after: $after);

        return $contact;
    }

    /**
     * @return array{name: string, email: string|null, phone: string|null, country: string|null, preferred_channel: string}
     */
    private function snapshot(Contact $contact): array
    {
        return [
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'country' => $contact->country,
            'preferred_channel' => $contact->preferred_channel->value,
        ];
    }

    private function preferredChannel(mixed $value): PreferredChannel
    {
        if ($value instanceof PreferredChannel) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return PreferredChannel::from($value);
        }

        return PreferredChannel::Email;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableCountry(mixed $value): ?string
    {
        $country = $this->nullableString($value);

        return $country === null ? null : strtoupper($country);
    }
}
