<?php

declare(strict_types=1);

namespace App\Support\Contacts;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

final class PhoneNumber
{
    public static function toE164(?string $phone, ?string $country): ?string
    {
        if ($phone === null) {
            return null;
        }

        $trimmed = trim($phone);

        if ($trimmed === '') {
            return null;
        }

        $region = self::region($country, $trimmed);

        if ($region === null) {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();

        try {
            $parsed = $util->parse($trimmed, $region);
        } catch (NumberParseException) {
            return null;
        }

        if (! $util->isValidNumber($parsed)) {
            return null;
        }

        return $util->format($parsed, PhoneNumberFormat::E164);
    }

    private static function region(?string $country, string $phone): ?string
    {
        if (is_string($country)) {
            $code = strtoupper(trim($country));

            if (preg_match('/^[A-Z]{2}$/', $code) === 1) {
                return $code;
            }
        }

        return str_starts_with($phone, '+') ? 'ZZ' : null;
    }
}
