<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Actions\Action;
use App\Enums\ContactType;
use App\Enums\PreferredChannel;
use App\Models\Contact;
use App\Support\Contacts\PhoneNumber;
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
     * @param  array{name: string, email?: string|null, phone?: string|null, country?: string|null, preferred_channel?: string|PreferredChannel|null, type?: string|ContactType|null, language?: string|null}  $data
     */
    public function handle(array $data): Contact
    {
        return $this->transaction(fn (): Contact => $this->resolve($data));
    }

    /**
     * @param  array{name: string, email?: string|null, phone?: string|null, country?: string|null, preferred_channel?: string|PreferredChannel|null, type?: string|ContactType|null, language?: string|null}  $data
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
        $type = $this->typeHint($data['type'] ?? null);
        $language = $this->language($data['language'] ?? null);
        $phone = $this->nullableString($data['phone'] ?? null);
        $country = $this->nullableCountry($data['country'] ?? null);
        $phoneE164 = PhoneNumber::toE164($phone, $country);

        $affected = (int) DB::affectingStatement(
            'insert into contacts (`name`, `email`, `phone`, `country`, `preferred_channel`, `type`, `language`, `phone_e164`, `created_at`, `updated_at`, `created_by`, `updated_by`)
             values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             on duplicate key update `id` = `id`',
            [
                $data['name'],
                $email,
                $phone,
                $country,
                $channel->value,
                $type->value,
                $language,
                $phoneE164,
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

        $contact = $contact->currentSurvivor();
        $contact->ensureUnsubscribeToken();

        if (self::createdFromInsertAffected($affected)) {
            History::record($contact, 'contact.created', after: $this->snapshot($contact));

            return $contact;
        }

        return $this->applyTypeHint($this->fillEmptyFields($contact, $data, $channel), $type);
    }

    /**
     * @param  array{name: string, email?: string|null, phone?: string|null, country?: string|null, preferred_channel?: string|PreferredChannel|null, type?: string|ContactType|null, language?: string|null}  $data
     */
    private function createWithoutEmail(array $data): Contact
    {
        $phone = $this->nullableString($data['phone'] ?? null);
        $country = $this->nullableCountry($data['country'] ?? null);
        $phoneE164 = PhoneNumber::toE164($phone, $country);

        if ($phoneE164 !== null) {
            $existing = Contact::query()
                ->notMerged()
                ->where('phone_e164', $phoneE164)
                ->orderBy('id')
                ->first();

            if ($existing instanceof Contact) {
                $channel = $this->preferredChannel($data['preferred_channel'] ?? null);

                return $this->applyTypeHint($this->fillEmptyFields($existing, $data, $channel), $this->typeHint($data['type'] ?? null));
            }
        }

        $contact = Contact::query()->create([
            'name' => $data['name'],
            'email' => null,
            'phone' => $phone,
            'country' => $country,
            'preferred_channel' => $this->preferredChannel($data['preferred_channel'] ?? null),
            'type' => $this->typeHint($data['type'] ?? null),
            'language' => $this->language($data['language'] ?? null),
            'phone_e164' => $phoneE164,
        ]);

        History::record($contact, 'contact.created', after: $this->snapshot($contact));

        return $contact;
    }

    /**
     * @param  array{name: string, email?: string|null, phone?: string|null, country?: string|null, preferred_channel?: string|PreferredChannel|null, type?: string|ContactType|null, language?: string|null}  $data
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

        if (isset($update['phone']) || isset($update['country'])) {
            $update['phone_e164'] = PhoneNumber::toE164(
                isset($update['phone']) ? (string) $update['phone'] : $contact->phone,
                isset($update['country']) ? (string) $update['country'] : $contact->country,
            );
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

    private function applyTypeHint(Contact $contact, ContactType $hint): Contact
    {
        if ($hint === ContactType::DirectPassenger) {
            return $contact;
        }

        if ($contact->type !== ContactType::DirectPassenger) {
            return $contact;
        }

        $before = ['type' => $contact->type->value];
        $after = ['type' => $hint->value];

        Contact::query()->whereKey($contact->id)->update([
            'type' => $hint->value,
            'updated_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        $contact->refresh();

        History::record($contact, 'contact.updated', before: $before, after: $after);

        return $contact;
    }

    /**
     * @return array{name: string, email: string|null, phone: string|null, country: string|null, preferred_channel: string, type: string, language: string}
     */
    private function snapshot(Contact $contact): array
    {
        return [
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'country' => $contact->country,
            'preferred_channel' => $contact->preferred_channel->value,
            'type' => $contact->type->value,
            'language' => $contact->language,
        ];
    }

    private function typeHint(mixed $value): ContactType
    {
        if ($value instanceof ContactType) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return ContactType::from($value);
        }

        return ContactType::DirectPassenger;
    }

    private function language(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return 'en';
        }

        $language = strtolower(trim($value));

        return preg_match('/^[a-z]{2}$/', $language) === 1 ? $language : 'en';
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
