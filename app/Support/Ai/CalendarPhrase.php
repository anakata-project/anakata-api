<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Support\BusinessTime;
use Carbon\CarbonImmutable;

/**
 * Turns an operator's date phrase into a Galápagos civil date.
 */
final class CalendarPhrase
{
    /**
     * @var array<string, int>
     */
    private const WEEKDAYS = [
        'monday' => 1,
        'lunes' => 1,
        'tuesday' => 2,
        'martes' => 2,
        'wednesday' => 3,
        'miercoles' => 3,
        'thursday' => 4,
        'jueves' => 4,
        'friday' => 5,
        'viernes' => 5,
        'saturday' => 6,
        'sabado' => 6,
        'sunday' => 0,
        'domingo' => 0,
    ];

    /**
     * @var array<string, int>
     */
    private const MONTHS = [
        'january' => 1,
        'enero' => 1,
        'february' => 2,
        'febrero' => 2,
        'march' => 3,
        'marzo' => 3,
        'april' => 4,
        'abril' => 4,
        'may' => 5,
        'mayo' => 5,
        'june' => 6,
        'junio' => 6,
        'july' => 7,
        'julio' => 7,
        'august' => 8,
        'agosto' => 8,
        'september' => 9,
        'septiembre' => 9,
        'setiembre' => 9,
        'october' => 10,
        'octubre' => 10,
        'november' => 11,
        'noviembre' => 11,
        'december' => 12,
        'diciembre' => 12,
    ];

    public static function resolve(string $phrase, ?CarbonImmutable $today = null): ?string
    {
        $today ??= BusinessTime::now()->startOfDay();
        $folded = self::fold($phrase);

        if ($folded === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $folded) === 1) {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $folded, BusinessTime::zone());

            return $parsed instanceof CarbonImmutable ? $parsed->format('Y-m-d') : null;
        }

        if (in_array($folded, ['today', 'hoy'], true)) {
            return $today->format('Y-m-d');
        }

        if (in_array($folded, ['tomorrow', 'manana'], true)) {
            return $today->addDay()->format('Y-m-d');
        }

        if (in_array($folded, ['yesterday', 'ayer'], true)) {
            return $today->subDay()->format('Y-m-d');
        }

        if (preg_match('/^(?:in|en) (\d+) (day|days|dia|dias)$/', $folded, $match) === 1) {
            return $today->addDays((int) $match[1])->format('Y-m-d');
        }

        if (preg_match('/^(?:in|en) (\d+) (week|weeks|semana|semanas)$/', $folded, $match) === 1) {
            return $today->addWeeks((int) $match[1])->format('Y-m-d');
        }

        if (preg_match('/^(?:next|proximo) ([a-z]+)$/', $folded, $match) === 1 && isset(self::WEEKDAYS[$match[1]])) {
            return $today->next(self::WEEKDAYS[$match[1]])->format('Y-m-d');
        }

        if (preg_match('/^(\d{1,2}) (?:de )?([a-z]+)(?: (\d{4}))?$/', $folded, $match) === 1 && isset(self::MONTHS[$match[2]])) {
            return self::civil((int) ($match[3] ?? $today->year), self::MONTHS[$match[2]], (int) $match[1]);
        }

        if (preg_match('/^([a-z]+) (\d{1,2})(?: (\d{4}))?$/', $folded, $match) === 1 && isset(self::MONTHS[$match[1]])) {
            return self::civil((int) ($match[3] ?? $today->year), self::MONTHS[$match[1]], (int) $match[2]);
        }

        return null;
    }

    private static function civil(int $year, int $month, int $day): ?string
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private static function fold(string $phrase): string
    {
        $value = mb_strtolower(trim($phrase));
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $folded = preg_replace('/\s+/', ' ', $value);

        return is_string($folded) ? $folded : $value;
    }
}
