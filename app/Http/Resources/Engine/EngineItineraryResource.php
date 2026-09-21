<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use App\Models\Itinerary;
use App\Support\Itineraries\Gradients;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Itinerary
 */
class EngineItineraryResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     slug: string,
     *     days: int,
     *     nights: int,
     *     embark: string,
     *     disembark: string,
     *     festive: bool,
     *     tagline: string,
     *     card: array{
     *         description: string,
     *         highlights: list<string>,
     *         chips: list<string>,
     *         hero_image: string|null,
     *         hero_alt: string,
     *         fallback_gradient: string
     *     },
     *     overview: string,
     *     detail: array{
     *         long_description: string,
     *         facts: list<array{0: string, 1: string}>,
     *         day_by_day: list<array{0: string, 1: string}>,
     *         included: list<string>,
     *         excluded: list<string>,
     *         faqs: list<array{0: string, 1: string}>
     *     },
     *     seo: array{title: string, description: string}
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug ?? '',
            'days' => $this->days,
            'nights' => $this->nights,
            'embark' => $this->embark,
            'disembark' => $this->disembark,
            'festive' => $this->festive,
            'tagline' => $this->tagline,
            'card' => [
                'description' => $this->card_description,
                'highlights' => $this->highlights,
                'chips' => $this->chips,
                'hero_image' => $this->heroImageUrl(),
                'hero_alt' => $this->hero_alt,
                'fallback_gradient' => Gradients::css($this->fallback_gradient),
            ],
            'overview' => $this->overview,
            'detail' => [
                'long_description' => $this->long_description,
                'facts' => $this->facts,
                'day_by_day' => $this->day_plan,
                'included' => $this->included,
                'excluded' => $this->excluded,
                'faqs' => $this->faqs,
            ],
            'seo' => [
                'title' => $this->meta_title,
                'description' => $this->meta_description,
            ],
        ];
    }

    private function heroImageUrl(): ?string
    {
        if (! is_string($this->hero_image_path) || $this->hero_image_path === '') {
            return null;
        }

        return Storage::disk('public')->url($this->hero_image_path);
    }
}
