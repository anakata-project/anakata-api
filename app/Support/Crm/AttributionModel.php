<?php

declare(strict_types=1);

namespace App\Support\Crm;

final class AttributionModel
{
    /**
     * The prototype's attribution table, written as the booking actually stores it.
     *
     * @return list<array{layer: string, captured_by: string, stored_on: string, used_for: string}>
     */
    public static function rows(): array
    {
        return [
            [
                'layer' => 'UTM source / medium / campaign / content / term',
                'captured_by' => 'ENGINE',
                'stored_on' => 'RMS booking — frozen utm_first and utm_last',
                'used_for' => 'Campaign ROI, paid-media optimisation',
            ],
            [
                'layer' => 'Main channel (8 values)',
                'captured_by' => 'CRM',
                'stored_on' => 'RMS booking, written once at creation',
                'used_for' => 'Revenue split D2C vs trade',
            ],
            [
                'layer' => 'Channel of origin (40 values)',
                'captured_by' => 'CRM',
                'stored_on' => 'RMS booking, written once at creation',
                'used_for' => 'Channel P&L, commission liability',
            ],
            [
                'layer' => 'Offer code',
                'captured_by' => 'ENGINE',
                'stored_on' => 'RMS booking price lines and promo code',
                'used_for' => 'Redemption, campaign revenue',
            ],
            [
                'layer' => 'Partner / agency ID',
                'captured_by' => 'RMS',
                'stored_on' => 'RMS booking and the commission ledger',
                'used_for' => 'Payouts, leakage control',
            ],
        ];
    }

    public const CONFLICT = 'If a booking carries a partner ID, the trade attribution wins over the marketing last-touch for commission. Marketing keeps the touch for media reporting. Both are stored and neither overwrites the other.';
}
