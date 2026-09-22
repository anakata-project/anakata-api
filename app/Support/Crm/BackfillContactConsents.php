<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Actions\Crm\RecordContactConsent;
use App\Enums\ConsentCapturePoint;
use App\Enums\ConsentDocument;
use App\Enums\ConsentPurpose;
use App\Models\Contact;
use App\Models\ContactConsent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class BackfillContactConsents
{
    public static function run(): int
    {
        $rows = DB::table('consents')
            ->join('bookings', 'bookings.id', '=', 'consents.booking_id')
            ->where('consents.document', ConsentDocument::Marketing->value)
            ->whereNotNull('bookings.contact_id')
            ->orderBy('consents.id')
            ->get([
                'consents.id',
                'consents.version',
                'consents.accepted_at',
                'consents.ip',
                'consents.withdrawn',
                'consents.recorded_by',
                'bookings.contact_id',
            ]);

        $inserted = 0;

        foreach ($rows as $row) {
            if (ContactConsent::query()->where('source_consent_id', $row->id)->exists()) {
                continue;
            }

            $contact = Contact::query()->find($row->contact_id);

            if (! $contact instanceof Contact) {
                continue;
            }

            $actor = is_numeric($row->recorded_by)
                ? User::query()->find((int) $row->recorded_by)
                : null;

            app(RecordContactConsent::class)->handle(
                $contact,
                ConsentPurpose::Marketing,
                granted: ! (bool) $row->withdrawn,
                version: (string) $row->version,
                capturePoint: ConsentCapturePoint::BookingLogBackfill,
                capturedAt: Carbon::parse((string) $row->accepted_at),
                ip: is_string($row->ip) ? $row->ip : null,
                recordedBy: $actor instanceof User ? $actor : null,
                sourceConsentId: (int) $row->id,
            );

            $inserted++;
        }

        return $inserted;
    }
}
