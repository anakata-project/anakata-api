<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\ConsentPurpose;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\User;
use App\Support\Iso;

final class ContactConsentView
{
    /**
     * @return array{
     *     current: list<array{
     *         purpose: string,
     *         label: string,
     *         granted: bool|null,
     *         version: string|null,
     *         captured_at: string|null,
     *         capture_point: string|null,
     *         recorded_by: array{id: int, name: string}|null,
     *         how_obtained: string|null,
     *         ip_present: bool
     *     }>,
     *     history: list<array{
     *         purpose: string,
     *         label: string,
     *         granted: bool,
     *         version: string,
     *         captured_at: string,
     *         capture_point: string,
     *         recorded_by: array{id: int, name: string}|null,
     *         how_obtained: string|null,
     *         ip_present: bool
     *     }>
     * }
     */
    public static function forContact(Contact $contact): array
    {
        $rows = ContactConsent::query()
            ->where('contact_id', $contact->id)
            ->with('recordedBy')
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->get();

        $latest = [];

        foreach ($rows as $row) {
            $purpose = $row->purpose->value;

            if (! isset($latest[$purpose])) {
                $latest[$purpose] = $row;
            }
        }

        $current = [];

        foreach (ConsentPurpose::cases() as $purpose) {
            $row = $latest[$purpose->value] ?? null;
            $current[] = $row instanceof ContactConsent
                ? self::row($row)
                : self::empty($purpose);
        }

        return [
            'current' => $current,
            'history' => $rows->map(fn (ContactConsent $row): array => self::row($row))->all(),
        ];
    }

    /**
     * @return array{
     *     purpose: string,
     *     label: string,
     *     granted: bool,
     *     version: string,
     *     captured_at: string,
     *     capture_point: string,
     *     recorded_by: array{id: int, name: string}|null,
     *     how_obtained: string|null,
     *     ip_present: bool
     * }
     */
    public static function row(ContactConsent $row): array
    {
        $actor = $row->recordedBy;

        return [
            'purpose' => $row->purpose->value,
            'label' => $row->purpose->label(),
            'granted' => $row->granted,
            'version' => $row->version,
            'captured_at' => Iso::utc($row->captured_at),
            'capture_point' => $row->capture_point->value,
            'recorded_by' => $actor instanceof User
                ? ['id' => $actor->id, 'name' => $actor->name]
                : null,
            'how_obtained' => $row->how_obtained,
            'ip_present' => is_string($row->ip) && $row->ip !== '',
        ];
    }

    /**
     * @return array{
     *     purpose: string,
     *     label: string,
     *     granted: null,
     *     version: null,
     *     captured_at: null,
     *     capture_point: null,
     *     recorded_by: null,
     *     how_obtained: null,
     *     ip_present: false
     * }
     */
    private static function empty(ConsentPurpose $purpose): array
    {
        return [
            'purpose' => $purpose->value,
            'label' => $purpose->label(),
            'granted' => null,
            'version' => null,
            'captured_at' => null,
            'capture_point' => null,
            'recorded_by' => null,
            'how_obtained' => null,
            'ip_present' => false,
        ];
    }
}
