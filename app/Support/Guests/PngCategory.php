<?php

declare(strict_types=1);

namespace App\Support\Guests;

use App\Enums\PngCategory as Category;
use App\Support\Config\Documents\EngineSettingsDocument;
use Carbon\CarbonImmutable;

final class PngCategory
{
    /**
     * @return array{category: Category, fee: int|null}
     */
    public static function for(
        ?CarbonImmutable $dob,
        ?string $nationality,
        bool $resident,
        CarbonImmutable $departureDate,
        EngineSettingsDocument $settings,
    ): array {
        $age = Age::at($dob, $departureDate);
        $code = $nationality === null || $nationality === '' ? null : $nationality;
        $png = $settings->fees->png;

        if ($age === null || $code === null) {
            return ['category' => Category::Pending, 'fee' => null];
        }

        if ($age < $png->exemptUnderAge) {
            return ['category' => Category::Exempt, 'fee' => 0];
        }

        if ($code === 'EC' || $resident) {
            return ['category' => Category::NationalOrResident, 'fee' => $png->nationalOrResident];
        }

        if (AndeanCommunity::contains($code)) {
            return $age > 12
                ? ['category' => Category::CanAdult, 'fee' => $png->canAdult]
                : ['category' => Category::CanMinor, 'fee' => $png->canMinor];
        }

        return $age > 12
            ? ['category' => Category::ForeignOver12, 'fee' => $png->foreignOver12]
            : ['category' => Category::Foreign12AndUnder, 'fee' => $png->foreign12AndUnder];
    }
}
