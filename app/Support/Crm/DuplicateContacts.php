<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Models\Contact;
use Illuminate\Support\Collection;

final class DuplicateContacts
{
    /**
     * @return list<array{a: Contact, b: Contact, reasons: list<string>}>
     */
    public static function pairs(): array
    {
        $contacts = Contact::query()
            ->notMerged()
            ->withDerived()
            ->orderBy('id')
            ->get();

        /** @var array<string, array{a: Contact, b: Contact, reasons: list<string>}> $pairs */
        $pairs = [];

        /** @var Collection<int, Collection<int, Contact>> $byPhone */
        $byPhone = $contacts
            ->filter(fn (Contact $contact): bool => is_string($contact->phone_e164) && $contact->phone_e164 !== '')
            ->groupBy('phone_e164');

        foreach ($byPhone as $group) {
            self::addCombinations($pairs, $group->values(), 'phone');
        }

        /** @var Collection<int, Collection<int, Contact>> $byNameCountry */
        $byNameCountry = $contacts
            ->filter(function (Contact $contact): bool {
                return Contact::normalizeName($contact->name) !== null && is_string($contact->country) && $contact->country !== '';
            })
            ->groupBy(fn (Contact $contact): string => (string) Contact::normalizeName($contact->name).'|'.$contact->country);

        foreach ($byNameCountry as $group) {
            self::addCombinations($pairs, $group->values(), 'name_country');
        }

        $sorted = array_values($pairs);
        usort($sorted, fn (array $left, array $right): int => $left['a']->id <=> $right['a']->id ?: $left['b']->id <=> $right['b']->id);

        return $sorted;
    }

    /**
     * @param  array<string, array{a: Contact, b: Contact, reasons: list<string>}>  $pairs
     * @param  Collection<int, Contact>  $group
     */
    private static function addCombinations(array &$pairs, Collection $group, string $reason): void
    {
        /** @var list<Contact> $items */
        $items = $group->values()->all();
        $count = count($items);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $left = $items[$i];
                $right = $items[$j];
                $key = $left->id < $right->id ? $left->id.':'.$right->id : $right->id.':'.$left->id;
                $a = $left->id < $right->id ? $left : $right;
                $b = $left->id < $right->id ? $right : $left;

                if (! isset($pairs[$key])) {
                    $pairs[$key] = ['a' => $a, 'b' => $b, 'reasons' => []];
                }

                if (! in_array($reason, $pairs[$key]['reasons'], true)) {
                    $pairs[$key]['reasons'][] = $reason;
                }
            }
        }
    }
}
