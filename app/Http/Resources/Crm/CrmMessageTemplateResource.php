<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Enums\AutomationKind;
use App\Models\MessageTemplate;
use App\Models\MessageTemplateVersion;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * @mixin MessageTemplate
 */
#[SchemaName('CrmMessageTemplateResource')]
class CrmMessageTemplateResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     key: string,
     *     name: string,
     *     kind: AutomationKind,
     *     published: CrmMessageTemplateVersionResource|null,
     *     draft: CrmMessageTemplateVersionResource|null
     * }
     */
    public function toArray(Request $request): array
    {
        $template = $this->resource;

        if (! $template instanceof MessageTemplate) {
            throw new LogicException('Template resource expected a message template.');
        }

        return [
            'key' => $template->key,
            'name' => $template->name,
            'kind' => $this->kind($template),
            'published' => $this->versionPayload($this->latest($template, published: true)),
            'draft' => $this->versionPayload($this->latest($template, published: false)),
        ];
    }

    private function kind(MessageTemplate $template): AutomationKind
    {
        return $template->kind;
    }

    private function versionPayload(?MessageTemplateVersion $version): ?CrmMessageTemplateVersionResource
    {
        return $version instanceof MessageTemplateVersion
            ? new CrmMessageTemplateVersionResource($version)
            : null;
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
