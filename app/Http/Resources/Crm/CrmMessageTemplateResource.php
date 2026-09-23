<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Models\MessageTemplate;
use App\Models\MessageTemplateVersion;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MessageTemplate
 */
#[SchemaName('CrmMessageTemplateResource')]
class CrmMessageTemplateResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $template = $this->resource;

        if (! $template instanceof MessageTemplate) {
            return [];
        }

        $published = $this->latest($template, published: true);
        $draft = $this->latest($template, published: false);

        return [
            'key' => $template->key,
            'name' => $template->name,
            'kind' => $template->kind->value,
            'published' => $published instanceof MessageTemplateVersion
                ? (new CrmMessageTemplateVersionResource($published))->resolve()
                : null,
            'draft' => $draft instanceof MessageTemplateVersion
                ? (new CrmMessageTemplateVersionResource($draft))->resolve()
                : null,
        ];
    }

    private function latest(MessageTemplate $template, bool $published): ?MessageTemplateVersion
    {
        $versions = $template->relationLoaded('versions')
            ? $template->versions
            : $template->versions()->get();

        $match = $versions->where('published', $published)->sortByDesc('version')->first();

        return $match instanceof MessageTemplateVersion ? $match : null;
    }
}
