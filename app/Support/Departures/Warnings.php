<?php

declare(strict_types=1);

namespace App\Support\Departures;

use App\Models\Departure;
use App\Support\Dates\Format;

final class Warnings
{
    /**
     * @return list<string>
     */
    public static function for(Departure $departure): array
    {
        $departure->loadMissing(['yacht', 'itinerary']);

        $warnings = [];

        $twin = Departure::query()
            ->where('yacht_id', '!=', $departure->yacht_id)
            ->whereDate('date', $departure->date->toDateString())
            ->with('yacht')
            ->first();

        if ($twin instanceof Departure && $twin->festive !== $departure->festive) {
            $code = $twin->yacht->code;
            $on = Format::calendar($twin->date);
            $warnings[] = $twin->festive
                ? "{$code}'s departure on {$on} is festive."
                : "{$code}'s departure on {$on} is not festive.";
        }

        $itinerary = $departure->itinerary;

        if ($departure->festive && ! $itinerary->festive) {
            $warnings[] = 'This departure is festive but itinerary '.$itinerary->code.' is not.';
        } elseif (! $departure->festive && $itinerary->festive) {
            $warnings[] = 'This departure is not festive but itinerary '.$itinerary->code.' is festive.';
        }

        return $warnings;
    }
}
