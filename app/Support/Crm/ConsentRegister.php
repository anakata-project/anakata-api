<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\ConsentPurpose;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

final class ConsentRegister
{
    /**
     * @return list<array{
     *     purpose: string,
     *     label: string,
     *     basis: string,
     *     opt_in_needed: bool,
     *     where_captured: string,
     *     contacts: int
     * }>
     */
    public static function rows(): array
    {
        $granted = self::grantedCounts();
        $contacts = Contact::query()->notMerged()->count();

        $purposes = [
            [
                'purpose' => 'TRANSACTIONAL',
                'label' => 'Transactional — booking, payment, pre-trip, documents',
                'basis' => 'Contract',
                'opt_in_needed' => false,
                'where_captured' => 'Booking request',
                'contacts' => $contacts,
            ],
            [
                'purpose' => ConsentPurpose::Marketing->value,
                'label' => 'Marketing — nurture, re-engagement, offers',
                'basis' => 'Consent',
                'opt_in_needed' => true,
                'where_captured' => 'Engine form · staff',
                'contacts' => $granted[ConsentPurpose::Marketing->value] ?? 0,
            ],
            [
                'purpose' => ConsentPurpose::Profiling->value,
                'label' => 'Profiling — segmentation and personalisation',
                'basis' => 'Consent',
                'opt_in_needed' => true,
                'where_captured' => 'not captured yet',
                'contacts' => $granted[ConsentPurpose::Profiling->value] ?? 0,
            ],
            [
                'purpose' => ConsentPurpose::Remarketing->value,
                'label' => 'Remarketing audiences — Meta / Google',
                'basis' => 'Consent',
                'opt_in_needed' => true,
                'where_captured' => 'not captured yet',
                'contacts' => $granted[ConsentPurpose::Remarketing->value] ?? 0,
            ],
            [
                'purpose' => ConsentPurpose::Whatsapp->value,
                'label' => 'WhatsApp messaging',
                'basis' => 'Consent',
                'opt_in_needed' => true,
                'where_captured' => 'not captured yet',
                'contacts' => $granted[ConsentPurpose::Whatsapp->value] ?? 0,
            ],
            [
                'purpose' => ConsentPurpose::Analytics->value,
                'label' => 'Analytics — engine behaviour',
                'basis' => 'Consent',
                'opt_in_needed' => true,
                'where_captured' => 'Engine banner',
                'contacts' => $granted[ConsentPurpose::Analytics->value] ?? 0,
            ],
        ];

        return $purposes;
    }

    /**
     * @return array<string, int>
     */
    private static function grantedCounts(): array
    {
        $counts = DB::select(<<<'SQL'
            SELECT latest.purpose, COUNT(*) AS contacts
            FROM (
                SELECT
                    contact_consents.contact_id,
                    contact_consents.purpose,
                    contact_consents.granted,
                    ROW_NUMBER() OVER (
                        PARTITION BY contact_consents.contact_id, contact_consents.purpose
                        ORDER BY contact_consents.captured_at DESC, contact_consents.id DESC
                    ) AS rn
                FROM contact_consents
                INNER JOIN contacts ON contacts.id = contact_consents.contact_id
                WHERE contacts.merged_into_id IS NULL
            ) AS latest
            WHERE latest.rn = 1 AND latest.granted = 1
            GROUP BY latest.purpose
        SQL);

        $byPurpose = [];

        foreach ($counts as $row) {
            $byPurpose[(string) $row->purpose] = (int) $row->contacts;
        }

        return $byPurpose;
    }
}
