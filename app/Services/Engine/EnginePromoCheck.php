<?php

declare(strict_types=1);

namespace App\Services\Engine;

use App\Enums\BookingType;
use App\Models\Departure;
use App\Models\Offer;
use App\Services\Pricing\ReservationQuoter;
use App\Support\IpHash;
use Illuminate\Support\Facades\Log;

final class EnginePromoCheck
{
    public function __construct(private ReservationQuoter $quoter) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{valid: bool, reason: string|null, line: string|null, applies_to: list<string>}
     */
    public function check(array $input, Departure $departure, ?string $ip): array
    {
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $quote = $this->quoter->quote([
            'departure_id' => $departure->id,
            'type' => BookingType::Cabin->value,
            'cabins' => $input['cabins'] ?? [],
            'promo_code' => $code,
        ], $departure);

        $appliesTo = [];

        foreach ($quote->parties as $party) {
            if ($party->quote === null) {
                continue;
            }

            foreach ($party->quote->lines as $line) {
                if (strcasecmp($line->code, $code) === 0) {
                    $appliesTo[] = $party->cabinLabel;
                    break;
                }
            }
        }

        if ($appliesTo !== []) {
            $offer = Offer::query()
                ->where('is_promo_code', true)
                ->whereRaw('UPPER(code) = ?', [$code])
                ->first();

            return [
                'valid' => true,
                'reason' => null,
                'line' => $offer instanceof Offer ? $offer->price_line : null,
                'applies_to' => $appliesTo,
            ];
        }

        $reason = $departure->festive
            ? 'This code does not apply to festive departures'
            : 'This code is not valid';

        Log::info('engine.promo.invalid', [
            'ip_hash' => IpHash::of($ip),
            'departure_id' => $departure->id,
            'reason' => $reason,
        ]);

        return [
            'valid' => false,
            'reason' => $reason,
            'line' => null,
            'applies_to' => [],
        ];
    }
}
