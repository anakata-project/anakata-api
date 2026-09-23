<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Models\MessageTemplateVersion;
use App\Support\Iso;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * @mixin MessageTemplateVersion
 */
#[SchemaName('CrmMessageTemplateVersionResource')]
class CrmMessageTemplateVersionResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     version: int,
     *     subject: string,
     *     body: array{paragraphs: list<string>, list: list<string>, cta: array{label: string, link_key: string}|null},
     *     variables: list<string>,
     *     published: bool,
     *     published_at: string|null,
     *     approval_reference: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        $version = $this->resource;

        if (! $version instanceof MessageTemplateVersion) {
            throw new LogicException('Template version resource expected a version.');
        }

        return [
            'version' => $version->version,
            'subject' => $version->subject,
            'body' => $this->body($version),
            'variables' => $version->variables,
            'published' => $version->published,
            'published_at' => Iso::utc($version->published_at),
            'approval_reference' => $version->approval_reference,
        ];
    }

    /**
     * @return array{paragraphs: list<string>, list: list<string>, cta: array{label: string, link_key: string}|null}
     */
    private function body(MessageTemplateVersion $version): array
    {
        /** @var array<string, mixed> $body */
        $body = $version->body;
        $cta = $body['cta'] ?? null;

        return [
            'paragraphs' => $this->strings($body['paragraphs'] ?? null),
            'list' => $this->strings($body['list'] ?? null),
            'cta' => is_array($cta) ? [
                'label' => (string) ($cta['label'] ?? ''),
                'link_key' => (string) ($cta['link_key'] ?? ''),
            ] : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $strings = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                $strings[] = $value;
            }
        }

        return $strings;
    }
}
