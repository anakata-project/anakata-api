<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Support\Itineraries\Defaults;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array<string, mixed> $resource
 */
class ItineraryDefaultsResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     status: string,
     *     sort_order: int,
     *     festive: bool,
     *     days: int,
     *     nights: int,
     *     embark: string,
     *     disembark: string,
     *     tagline: string,
     *     hero_alt: string,
     *     fallback_gradient: string,
     *     fallback_gradient_key: string,
     *     gradients: list<array{key: string, css: string}>,
     *     card_description: string,
     *     overview: string,
     *     long_description: string,
     *     highlights: list<string>,
     *     chips: list<string>,
     *     facts: list<array{0: string, 1: string}>,
     *     day_plan: list<array{0: string, 1: string}>,
     *     included: list<string>,
     *     excluded: list<string>,
     *     faqs: list<array{0: string, 1: string}>,
     *     slug: string|null,
     *     meta_title: string,
     *     meta_description: string
     * }
     */
    public function toArray(Request $request): array
    {
        return Defaults::payload();
    }
}
