<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Models\Itinerary;
use App\Support\Itineraries\Completeness;
use App\Support\Itineraries\Gradients;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Itinerary
 */
class ItineraryResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     status: string,
     *     sort_order: int,
     *     festive: bool,
     *     days: int,
     *     nights: int,
     *     embark: string,
     *     disembark: string,
     *     tagline: string,
     *     hero_image_url: string|null,
     *     hero_alt: string,
     *     fallback_gradient: string,
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
     *     meta_description: string,
     *     completeness: array{pct: int, missing: list<string>, blocking: list<string>},
     *     departures_count: int,
     *     live_departures_count: int
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status->value,
            'sort_order' => $this->sort_order,
            'festive' => $this->festive,
            'days' => $this->days,
            'nights' => $this->nights,
            'embark' => $this->embark,
            'disembark' => $this->disembark,
            'tagline' => $this->tagline,
            'hero_image_url' => $this->heroImageUrl(),
            'hero_alt' => $this->hero_alt,
            'fallback_gradient' => Gradients::css($this->fallback_gradient),
            'card_description' => $this->card_description,
            'overview' => $this->overview,
            'long_description' => $this->long_description,
            'highlights' => $this->highlights,
            'chips' => $this->chips,
            'facts' => $this->facts,
            'day_plan' => $this->day_plan,
            'included' => $this->included,
            'excluded' => $this->excluded,
            'faqs' => $this->faqs,
            'slug' => $this->slug,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'completeness' => Completeness::for($this->resource)->toArray(),
            'departures_count' => $this->departuresCount(),
            'live_departures_count' => $this->liveDeparturesCount(),
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
