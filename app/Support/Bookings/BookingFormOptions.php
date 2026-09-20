<?php

declare(strict_types=1);

namespace App\Support\Bookings;

use App\Enums\ChannelOfOrigin;
use App\Enums\ChannelOfOriginGroup;
use App\Enums\MainChannel;
use App\Enums\PreferredChannel;
use App\Services\Config\CurrentConfig;

final class BookingFormOptions
{
    /**
     * @return array{
     *     main: list<array{value: string, label: string, trade: bool}>,
     *     origin: list<array{group: string, options: list<array{value: string, label: string}>}>,
     *     preferred: list<array{value: string, label: string}>,
     *     guests: array{child_min_age: int, child_max_age: int, max_per_cabin: int}
     * }
     */
    public static function fromConfig(CurrentConfig $config): array
    {
        $grouped = [];

        foreach (ChannelOfOrigin::cases() as $origin) {
            $grouped[$origin->group()->value][] = [
                'value' => $origin->value,
                'label' => $origin->label(),
            ];
        }

        $origin = [];

        foreach (ChannelOfOriginGroup::cases() as $group) {
            $origin[] = [
                'group' => $group->value,
                'options' => $grouped[$group->value] ?? [],
            ];
        }

        $guests = $config->engineSettings()->guests;

        return [
            'main' => array_map(
                fn (MainChannel $channel): array => [
                    'value' => $channel->value,
                    'label' => $channel->label(),
                    'trade' => $channel->isTrade(),
                ],
                MainChannel::cases(),
            ),
            'origin' => $origin,
            'preferred' => array_map(
                fn (PreferredChannel $channel): array => [
                    'value' => $channel->value,
                    'label' => $channel->label(),
                ],
                PreferredChannel::cases(),
            ),
            'guests' => [
                'child_min_age' => $guests->childMinAge,
                'child_max_age' => $guests->childMaxAge,
                'max_per_cabin' => $guests->maxPerCabin,
            ],
        ];
    }
}
