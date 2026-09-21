<?php

declare(strict_types=1);

namespace App\Support\Offers;

use App\Enums\OfferChannel;
use App\Enums\OfferType;
use App\Models\Itinerary;
use App\Models\Offer;
use Illuminate\Validation\ValidationException;

final class OfferGuardrails
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function normalize(array $attributes): array
    {
        if (isset($attributes['code']) && is_string($attributes['code'])) {
            $attributes['code'] = strtoupper(trim($attributes['code']));
        }

        $channel = self::channel($attributes['channel'] ?? null);
        $isPromo = (bool) ($attributes['is_promo_code'] ?? false);

        if ($channel === OfferChannel::B2B || $isPromo) {
            $attributes['show_on_card'] = false;
            $attributes['show_on_departures'] = false;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function validate(array $attributes, ?Offer $existing = null): void
    {
        $errors = [];

        $type = self::type($attributes['type'] ?? null);
        $channel = self::channel($attributes['channel'] ?? null);

        if ($type === OfferType::Value) {
            $text = trim((string) ($attributes['value_text'] ?? ''));

            if ($text === '') {
                $errors['value_text'] = ['Describe the value-add.'];
            }
        } elseif ($type instanceof OfferType) {
            $value = $attributes['value'] ?? null;

            if (! is_numeric($value) || (int) $value <= 0) {
                $errors['value'] = ['Enter a value above 0.'];
            }
        }

        if ($type === OfferType::Commission && $channel === OfferChannel::D2C) {
            $errors['channel'] = ['Partner commission offers must be B2B or All channels.'];
        }

        $cabins = self::stringList($attributes['cabin_types'] ?? []);

        if ($cabins === []) {
            $errors['cabin_types'] = ['Pick at least one cabin type.'];
        }

        $itineraries = self::stringList($attributes['itinerary_codes'] ?? []);

        if ($itineraries === []) {
            $errors['itinerary_codes'] = ['Pick at least one itinerary.'];
        } else {
            $festive = Itinerary::query()
                ->where('festive', true)
                ->whereIn('code', $itineraries)
                ->pluck('code')
                ->all();

            if ($festive !== []) {
                $errors['itinerary_codes'] = ['A festive itinerary can never be on an offer.'];
            }
        }

        $bookingFrom = self::dateString($attributes['booking_from'] ?? null);
        $bookingTo = self::dateString($attributes['booking_to'] ?? null);

        if ($bookingFrom !== null && $bookingTo !== null && $bookingFrom > $bookingTo) {
            $errors['booking_to'] = ['Booking window ends before it starts.'];
        }

        $travelFrom = self::dateString($attributes['travel_from'] ?? null);
        $travelTo = self::dateString($attributes['travel_to'] ?? null);

        if ($travelFrom !== null && $travelTo !== null && $travelFrom > $travelTo) {
            $errors['travel_to'] = ['Travel window ends before it starts.'];
        }

        if ($existing instanceof Offer && $existing->getOriginal('first_live_at') !== null) {
            $proposed = isset($attributes['code']) && is_string($attributes['code'])
                ? strtoupper(trim($attributes['code']))
                : $existing->code;
            $original = (string) ($existing->getOriginal('code') ?? $existing->code);

            if ($proposed !== $original) {
                $errors['code'] = ['The offer code cannot change after the offer has gone live. Pause it and create a new offer.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private static function type(mixed $value): ?OfferType
    {
        if ($value instanceof OfferType) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return OfferType::tryFrom($value);
        }

        return null;
    }

    private static function channel(mixed $value): ?OfferChannel
    {
        if ($value instanceof OfferChannel) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return OfferChannel::tryFrom($value);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $list = [];

        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $list[] = $item;
            }
        }

        return array_values(array_unique($list));
    }

    private static function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) ? $value : null;
    }
}
