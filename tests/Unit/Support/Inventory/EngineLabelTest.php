<?php

declare(strict_types=1);

use App\Enums\DepartureStatus;
use App\Enums\ItineraryStatus;
use App\Support\Inventory\EngineLabel;

test('engine label follows the documented precedence', function (array $input, array $expected): void {
    expect(EngineLabel::for(
        $input['status'],
        $input['itinerary'],
        $input['chartered'],
        $input['free'],
        $input['held'],
        $input['threshold'],
        $input['waitlist'],
    ))->toBe($expected);
})->with([
    'hidden' => [
        [
            'status' => DepartureStatus::Hidden,
            'itinerary' => ItineraryStatus::Published,
            'chartered' => false,
            'free' => 9,
            'held' => 0,
            'threshold' => 3,
            'waitlist' => true,
        ],
        ['code' => 'NOT_SHOWN', 'text' => 'NOT SHOWN', 'tone' => 'wait'],
    ],
    'draft itinerary' => [
        [
            'status' => DepartureStatus::OnSale,
            'itinerary' => ItineraryStatus::Draft,
            'chartered' => false,
            'free' => 9,
            'held' => 0,
            'threshold' => 3,
            'waitlist' => true,
        ],
        ['code' => 'NOT_SHOWN', 'text' => 'NOT SHOWN', 'tone' => 'wait'],
    ],
    'chartered beats closed' => [
        [
            'status' => DepartureStatus::Closed,
            'itinerary' => ItineraryStatus::Published,
            'chartered' => true,
            'free' => 0,
            'held' => 0,
            'threshold' => 3,
            'waitlist' => true,
        ],
        ['code' => 'CHARTERED', 'text' => 'CHARTERED — NOT SHOWN', 'tone' => 'wait'],
    ],
    'closed' => [
        [
            'status' => DepartureStatus::Closed,
            'itinerary' => ItineraryStatus::Published,
            'chartered' => false,
            'free' => 5,
            'held' => 0,
            'threshold' => 3,
            'waitlist' => true,
        ],
        ['code' => 'CLOSED', 'text' => 'CLOSED — ENQUIRE', 'tone' => 'comp'],
    ],
    'charter status' => [
        [
            'status' => DepartureStatus::Charter,
            'itinerary' => ItineraryStatus::Published,
            'chartered' => false,
            'free' => 9,
            'held' => 0,
            'threshold' => 3,
            'waitlist' => true,
        ],
        ['code' => 'CHARTER', 'text' => 'PRIVATE CHARTER ONLY', 'tone' => 'pend'],
    ],
    'limited before full' => [
        [
            'status' => DepartureStatus::OnSale,
            'itinerary' => ItineraryStatus::Published,
            'chartered' => false,
            'free' => 0,
            'held' => 2,
            'threshold' => 3,
            'waitlist' => true,
        ],
        ['code' => 'LIMITED', 'text' => 'LIMITED AVAILABILITY', 'tone' => 'hold'],
    ],
    'full waitlist' => [
        [
            'status' => DepartureStatus::OnSale,
            'itinerary' => ItineraryStatus::Published,
            'chartered' => false,
            'free' => 0,
            'held' => 0,
            'threshold' => 3,
            'waitlist' => true,
        ],
        ['code' => 'FULL', 'text' => 'FULL · WAITLIST', 'tone' => 'canc'],
    ],
    'full without waitlist' => [
        [
            'status' => DepartureStatus::OnSale,
            'itinerary' => ItineraryStatus::Published,
            'chartered' => false,
            'free' => 0,
            'held' => 0,
            'threshold' => 3,
            'waitlist' => false,
        ],
        ['code' => 'FULL', 'text' => 'FULL', 'tone' => 'canc'],
    ],
    'only one cabin' => [
        [
            'status' => DepartureStatus::OnSale,
            'itinerary' => ItineraryStatus::Published,
            'chartered' => false,
            'free' => 1,
            'held' => 0,
            'threshold' => 3,
            'waitlist' => true,
        ],
        ['code' => 'ONLY_N_LEFT', 'text' => 'ONLY 1 CABIN LEFT', 'tone' => 'hold'],
    ],
    'only three cabins' => [
        [
            'status' => DepartureStatus::OnSale,
            'itinerary' => ItineraryStatus::Published,
            'chartered' => false,
            'free' => 3,
            'held' => 0,
            'threshold' => 3,
            'waitlist' => true,
        ],
        ['code' => 'ONLY_N_LEFT', 'text' => 'ONLY 3 CABINS LEFT', 'tone' => 'hold'],
    ],
    'available' => [
        [
            'status' => DepartureStatus::OnSale,
            'itinerary' => ItineraryStatus::Published,
            'chartered' => false,
            'free' => 4,
            'held' => 0,
            'threshold' => 3,
            'waitlist' => true,
        ],
        ['code' => 'AVAILABLE', 'text' => 'AVAILABLE', 'tone' => 'conf'],
    ],
]);
