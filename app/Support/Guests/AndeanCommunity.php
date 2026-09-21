<?php

declare(strict_types=1);

namespace App\Support\Guests;

final class AndeanCommunity
{
    /**
     * Andean Community members other than Ecuador.
     * Consejo de Gobierno del Régimen Especial de Galápagos, Res. 002-CGREG-24-02-2024.
     *
     * @var list<string>
     */
    public const CODES = ['CO', 'PE', 'BO'];

    public static function contains(?string $nationality): bool
    {
        return $nationality !== null && in_array($nationality, self::CODES, true);
    }
}
