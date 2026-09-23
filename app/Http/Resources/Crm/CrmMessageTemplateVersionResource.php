<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Models\MessageTemplateVersion;
use App\Support\Iso;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MessageTemplateVersion
 */
#[SchemaName('CrmMessageTemplateVersionResource')]
class CrmMessageTemplateVersionResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->resource;

        if (! $version instanceof MessageTemplateVersion) {
            return [];
        }

        return [
            'version' => $version->version,
            'subject' => $version->subject,
            'body' => $version->body,
            'variables' => $version->variables,
            'published' => $version->published,
            'published_at' => Iso::utc($version->published_at),
            'approval_reference' => $version->approval_reference,
        ];
    }
}
