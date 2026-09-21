<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Actions\Action;
use App\Enums\ContactType;
use App\Enums\PreferredChannel;
use App\Exceptions\ConflictException;
use App\Models\Contact;
use App\Models\User;
use App\Support\History\History;

final class UpdateContact extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Contact $contact, array $data, User $actor): Contact
    {
        return $this->transaction(function () use ($contact, $data, $actor): Contact {
            $fields = ['name', 'email', 'phone', 'country', 'language', 'preferred_channel', 'type'];
            $before = [];
            $after = [];

            foreach ($fields as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                $next = $this->normalize($field, $data[$field]);
                $current = $this->current($contact, $field);

                if ($current === $next) {
                    continue;
                }

                if ($field === 'email' && is_string($next)) {
                    $other = Contact::query()
                        ->where('email', $next)
                        ->whereKeyNot($contact->id)
                        ->first();

                    if ($other instanceof Contact) {
                        throw new ConflictException(
                            'That email belongs to contact #'.$other->id.' ('.$other->name.'). Merge the contacts to keep a single record.',
                        );
                    }
                }

                $before[$field] = $current;
                $after[$field] = $next;
                $contact->setAttribute($field, $next);
            }

            if ($after === []) {
                return $contact;
            }

            $contact->save();

            History::record(
                $contact,
                'contact.updated',
                before: ['fields' => array_keys($before)],
                after: ['fields' => array_keys($after)],
                actor: $actor,
            );

            return $contact->fresh() ?? $contact;
        });
    }

    private function current(Contact $contact, string $field): mixed
    {
        $value = $contact->getAttribute($field);

        if ($value instanceof PreferredChannel || $value instanceof ContactType) {
            return $value->value;
        }

        return $value;
    }

    private function normalize(string $field, mixed $value): mixed
    {
        if ($field === 'preferred_channel') {
            if ($value instanceof PreferredChannel) {
                return $value->value;
            }

            return PreferredChannel::from((string) $value)->value;
        }

        if ($field === 'type') {
            if ($value instanceof ContactType) {
                return $value->value;
            }

            return ContactType::from((string) $value)->value;
        }

        if ($field === 'email') {
            return Contact::normalizeEmail(is_string($value) ? $value : null);
        }

        if ($field === 'language') {
            return is_string($value) ? strtolower(trim($value)) : 'en';
        }

        if ($field === 'country') {
            if (! is_string($value) || trim($value) === '') {
                return null;
            }

            return strtoupper(trim($value));
        }

        if ($field === 'phone') {
            if (! is_string($value) || trim($value) === '') {
                return null;
            }

            return trim($value);
        }

        if ($field === 'name') {
            return is_string($value) ? trim($value) : $value;
        }

        return $value;
    }
}
