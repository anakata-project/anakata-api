<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\DealStage;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Deal;
use App\Services\Config\CurrentConfig;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class DealDrawer
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Deal $deal): array
    {
        $deal->loadMissing(['contact', 'owner']);
        $rules = app(CurrentConfig::class)->businessRules();
        $projected = self::projected($deal);
        $stage = DealStage::tryFrom($projected) ?? DealStage::NewLead;
        $value = self::value($deal);
        $entered = CarbonImmutable::instance($deal->stage_entered_at);
        $sla = $stage->isOpen()
            ? [
                'state' => DealSla::assess($stage, $entered, CarbonImmutable::now(), $rules),
                'label' => DealSla::label($stage, $rules),
            ]
            : ['state' => null, 'label' => 'SYSTEM-SET'];

        return [
            'id' => $deal->id,
            'title' => $deal->title,
            'type' => $deal->type->value,
            'stage' => $stage->value,
            'owner' => $deal->owner_id === null || $deal->owner === null ? null : [
                'id' => $deal->owner->id,
                'name' => $deal->owner->name,
            ],
            'value' => $value,
            'value_label' => $deal->isBound() ? 'FROM RMS' : 'CRM ESTIMATE',
            'sla' => $sla,
            'booking' => self::booking($deal),
            'contact_id' => $deal->contact_id,
        ];
    }

    private static function projected(Deal $deal): string
    {
        $row = DB::selectOne(
            'SELECT ('.DealStages::stageSql().') AS stage FROM deals WHERE deals.id = ?',
            [$deal->id],
        );

        if (is_object($row) && is_string($row->stage ?? null) && $row->stage !== '') {
            return $row->stage;
        }

        return $deal->stage instanceof DealStage ? $deal->stage->value : '';
    }

    private static function value(Deal $deal): int
    {
        $row = DB::selectOne(
            'SELECT ('.DealStages::valueSql().') AS value_amount FROM deals WHERE deals.id = ?',
            [$deal->id],
        );

        return is_object($row) ? (int) ($row->value_amount ?? 0) : 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function booking(Deal $deal): ?array
    {
        if (! $deal->isBound()) {
            return null;
        }

        $sql = DealStages::bookingColumnSql('bookings.id');
        $picked = DB::selectOne('SELECT ('.$sql.') AS booking_id FROM deals WHERE deals.id = ?', [$deal->id]);
        $bookingId = is_object($picked) ? (int) ($picked->booking_id ?? 0) : 0;
        $booking = $bookingId > 0 ? Booking::query()->with(['departure', 'cabin', 'agency'])->find($bookingId) : null;

        if (! $booking instanceof Booking) {
            return null;
        }

        $charges = self::money($booking);
        $codes = self::offerCodes($booking);

        return [
            'reference' => OpenDealReference::of($booking, $deal),
            'status' => $booking->status->value,
            'departure_date' => self::departureDate($booking),
            'cabin' => $booking->cabin?->label,
            'charges_total' => $charges['charges'],
            'paid' => $charges['paid'],
            'balance' => $charges['balance'],
            'agency' => $booking->agency instanceof Agency ? [
                'id' => $booking->agency->id,
                'name' => $booking->agency->name,
            ] : null,
            'offer_codes' => $codes,
            'main_channel' => $booking->main_channel->value,
            'channel_of_origin' => $booking->channel_of_origin->value,
            'utm_first' => $booking->utm_first,
        ];
    }

    /**
     * @return array{charges: int, paid: int, balance: int}
     */
    private static function money(Booking $booking): array
    {
        [$balanceSql, $balanceBindings] = Booking::balanceSql();
        [$paidSql, $paidBindings] = Booking::paidSql();
        $charges = Booking::chargesTotalSql();
        $row = DB::selectOne(
            'SELECT ('.$charges.') AS charges, ('.$paidSql.') AS paid, ('.$balanceSql.') AS balance FROM bookings WHERE bookings.id = ?',
            [...$paidBindings, ...$balanceBindings, $booking->id],
        );

        return [
            'charges' => (int) ($row->charges ?? 0),
            'paid' => (int) ($row->paid ?? 0),
            'balance' => (int) ($row->balance ?? 0),
        ];
    }

    private static function departureDate(Booking $booking): string
    {
        return $booking->departure->date->format('Y-m-d');
    }

    /**
     * @return list<string>
     */
    private static function offerCodes(Booking $booking): array
    {
        $codes = [];

        if (is_string($booking->promo_code) && $booking->promo_code !== '') {
            $codes[] = $booking->promo_code;
        }

        foreach ($booking->price_lines as $line) {
            if ($line['code'] !== '' && ! in_array($line['code'], $codes, true)) {
                $codes[] = $line['code'];
            }
        }

        return $codes;
    }
}
