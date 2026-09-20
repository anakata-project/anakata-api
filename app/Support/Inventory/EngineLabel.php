<?php

declare(strict_types=1);

namespace App\Support\Inventory;

use App\Enums\DepartureStatus;
use App\Enums\EngineLabelCode;
use App\Enums\EngineLabelTone;
use App\Enums\ItineraryStatus;

final class EngineLabel
{
    /**
     * @return array{code: string, text: string, tone: string}
     */
    public static function for(
        DepartureStatus $status,
        ItineraryStatus $itineraryStatus,
        bool $chartered,
        int $free,
        int $held,
        int $urgencyThreshold,
        bool $waitlistEnabled,
    ): array {
        if ($status === DepartureStatus::Hidden || $itineraryStatus !== ItineraryStatus::Published) {
            return self::pack(EngineLabelCode::NotShown, 'NOT SHOWN', EngineLabelTone::Wait);
        }

        if ($chartered) {
            return self::pack(EngineLabelCode::Chartered, 'CHARTERED — NOT SHOWN', EngineLabelTone::Wait);
        }

        if ($status === DepartureStatus::Closed) {
            return self::pack(EngineLabelCode::Closed, 'CLOSED — ENQUIRE', EngineLabelTone::Comp);
        }

        if ($status === DepartureStatus::Charter) {
            return self::pack(EngineLabelCode::Charter, 'PRIVATE CHARTER ONLY', EngineLabelTone::Pend);
        }

        if ($free === 0 && $held > 0) {
            return self::pack(EngineLabelCode::Limited, 'LIMITED AVAILABILITY', EngineLabelTone::Hold);
        }

        if ($free === 0) {
            $text = $waitlistEnabled ? 'FULL · WAITLIST' : 'FULL';

            return self::pack(EngineLabelCode::Full, $text, EngineLabelTone::Canc);
        }

        if ($free <= $urgencyThreshold) {
            $noun = $free === 1 ? 'CABIN' : 'CABINS';

            return self::pack(EngineLabelCode::OnlyNLeft, 'ONLY '.$free.' '.$noun.' LEFT', EngineLabelTone::Hold);
        }

        return self::pack(EngineLabelCode::Available, 'AVAILABLE', EngineLabelTone::Conf);
    }

    /**
     * @return array{code: string, text: string, tone: string}
     */
    private static function pack(EngineLabelCode $code, string $text, EngineLabelTone $tone): array
    {
        return [
            'code' => $code->value,
            'text' => $text,
            'tone' => $tone->value,
        ];
    }
}
