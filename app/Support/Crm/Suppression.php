<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\ConsentPurpose;
use App\Enums\DeliveryStatus;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\Delivery;
use App\Models\ErasureLog;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

final class Suppression
{
    /**
     * Marketing consent withdrawn or never given, an erasure, or a hard bounce.
     * An unsubscribe is a marketing-consent withdrawal (Q7), so it is already here.
     *
     * @return array{match: string, items: list<array<string, mixed>>}
     */
    public static function conditions(): array
    {
        return [
            'match' => 'any',
            'items' => [
                ['field' => 'consent', 'operator' => 'eq', 'value' => 'never'],
                ['field' => 'consent', 'operator' => 'eq', 'value' => 'withdrawn'],
                ['field' => 'erasure', 'operator' => 'eq', 'value' => true],
                ['field' => 'hard_bounce', 'operator' => 'eq', 'value' => true],
            ],
        ];
    }

    public static function applies(Contact $contact): bool
    {
        return self::consentMissingOrWithdrawn($contact)
            || ErasureLog::query()->where('contact_id', $contact->id)->exists()
            || self::hardBounced($contact);
    }

    public static function sql(): string
    {
        $compiled = SegmentCompiler::compile(self::conditions());

        if ($compiled['bindings'] !== []) {
            throw new RuntimeException('Suppression SQL must not take bindings.');
        }

        return $compiled['sql'];
    }

    private static function consentMissingOrWithdrawn(Contact $contact): bool
    {
        $latest = ContactConsent::query()
            ->where('contact_id', $contact->id)
            ->where('purpose', ConsentPurpose::Marketing)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();

        return ! $latest instanceof ContactConsent || ! $latest->granted;
    }

    private static function hardBounced(Contact $contact): bool
    {
        return Delivery::query()
            ->where('status', DeliveryStatus::HardBounce)
            ->where(function (Builder $query) use ($contact): void {
                $query->whereHas('booking', function (Builder $booking) use ($contact): void {
                    $booking->where('contact_id', $contact->id);
                });

                if (is_string($contact->email) && $contact->email !== '') {
                    $query->orWhereJsonContains('to', strtolower($contact->email));
                }
            })
            ->exists();
    }
}
