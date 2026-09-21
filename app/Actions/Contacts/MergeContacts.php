<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Actions\Action;
use App\Models\Contact;
use App\Models\ContactAlias;
use App\Models\ContactMerge;
use App\Models\User;
use App\Support\Contacts\PhoneNumber;
use App\Support\Crm\ContactReferences;
use App\Support\History\History;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class MergeContacts extends Action
{
    /**
     * @return array{merge: ContactMerge, survivor: Contact, loser: Contact, swapped: bool}
     */
    public function handle(Contact $chosen, Contact $other, string $reason, User $actor): array
    {
        return $this->transaction(function () use ($chosen, $other, $reason, $actor): array {
            $left = $this->resolvedLive($chosen);
            $right = $this->resolvedLive($other);

            if ($left->id === $right->id) {
                throw new HttpException(422, 'Those records are already the same contact.');
            }

            $lowerId = min($left->id, $right->id);
            $higherId = max($left->id, $right->id);

            $first = Contact::query()->whereKey($lowerId)->lockForUpdate()->firstOrFail();
            $second = Contact::query()->whereKey($higherId)->lockForUpdate()->firstOrFail();

            $this->assertNotAlreadyMerged($first);
            $this->assertNotAlreadyMerged($second);

            $survivor = $first;
            $loser = $second;
            $swapped = $left->id !== $survivor->id;

            $repointed = ContactReferences::repoint($loser->id, $survivor->id);
            $loserFields = $this->ownedSnapshot($loser);

            if ($this->isEmpty($survivor->email) && ! $this->isEmpty($loser->email)) {
                Contact::query()->whereKey($loser->id)->update([
                    'email' => null,
                    'updated_at' => now(),
                    'updated_by' => $actor->id,
                ]);
                $loser->email = null;
            }

            $filled = $this->fillEmptyOwned($survivor, $loser, $actor, $loserFields);

            $merge = ContactMerge::query()->create([
                'survivor_id' => $survivor->id,
                'loser_id' => $loser->id,
                'reason' => $reason,
                'merged_by' => $actor->id,
                'merged_at' => now(),
                'repointed_rows' => $repointed,
                'merged_identifiers' => [
                    'email' => $loserFields['email'],
                    'phone' => $loserFields['phone'],
                    'phone_e164' => $loserFields['phone_e164'],
                ],
                'loser_fields' => $loserFields,
                'survivor_filled' => $filled,
            ]);

            ContactAlias::query()->create([
                'alias_id' => $loser->id,
                'contact_id' => $survivor->id,
                'merge_id' => $merge->id,
            ]);

            Contact::query()->whereKey($loser->id)->update([
                'merged_into_id' => $survivor->id,
                'updated_at' => now(),
                'updated_by' => $actor->id,
            ]);

            $after = [
                'survivor_id' => $survivor->id,
                'loser_id' => $loser->id,
                'merge_id' => $merge->id,
                'fields' => array_keys($filled),
            ];

            History::record($survivor, 'contact.merged', after: $after, reason: $reason, actor: $actor);
            History::record($loser, 'contact.merged', after: $after, reason: $reason, actor: $actor);

            return [
                'merge' => $merge->fresh() ?? $merge,
                'survivor' => $survivor->fresh() ?? $survivor,
                'loser' => $loser->fresh() ?? $loser,
                'swapped' => $swapped,
            ];
        });
    }

    private function resolvedLive(Contact $contact): Contact
    {
        $resolved = Contact::resolveIdentity($contact->resolvedFromAliasId ?? $contact->id) ?? $contact->currentSurvivor();

        if ($resolved->merged_into_id !== null && $resolved->id === $contact->id) {
            throw new HttpException(422, 'Contact #'.$contact->id.' is already merged and has no alias path.');
        }

        return $resolved;
    }

    private function assertNotAlreadyMerged(Contact $contact): void
    {
        if ($contact->merged_into_id !== null) {
            throw new HttpException(422, 'Contact #'.$contact->id.' is already merged.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function ownedSnapshot(Contact $contact): array
    {
        return [
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'country' => $contact->country,
            'language' => $contact->language,
            'preferred_channel' => $contact->preferred_channel->value,
            'type' => $contact->type->value,
            'phone_e164' => $contact->phone_e164,
            'first_touch' => $contact->first_touch,
            'last_touch' => $contact->last_touch,
        ];
    }

    /**
     * @param  array<string, mixed>  $loserFields
     * @return array<string, mixed>
     */
    private function fillEmptyOwned(Contact $survivor, Contact $loser, User $actor, array $loserFields): array
    {
        $fillable = ['email', 'phone', 'country', 'first_touch', 'last_touch'];
        $filled = [];
        $update = [];

        foreach ($fillable as $field) {
            $current = $survivor->getAttribute($field);
            $incoming = $loserFields[$field] ?? $loser->getAttribute($field);

            if (! $this->isEmpty($current) || $this->isEmpty($incoming)) {
                continue;
            }

            $filled[$field] = $incoming;
            $update[$field] = $incoming;
        }

        if (isset($update['phone']) || isset($update['country'])) {
            $phone = $update['phone'] ?? $survivor->phone;
            $country = $update['country'] ?? $survivor->country;
            $update['phone_e164'] = PhoneNumber::toE164(
                is_string($phone) ? $phone : null,
                is_string($country) ? $country : null,
            );
            $filled['phone_e164'] = $update['phone_e164'];
        }

        if ($update === []) {
            return [];
        }

        Contact::query()->whereKey($survivor->id)->update([
            ...self::encodeAttributes($update),
            'updated_at' => now(),
            'updated_by' => $actor->id,
        ]);

        $survivor->refresh();

        return $filled;
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        return is_array($value) && $value === [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function encodeAttributes(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            if (is_array($value)) {
                $attributes[$key] = json_encode($value);
            }
        }

        return $attributes;
    }
}
