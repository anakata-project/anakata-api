<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Models\MessageTemplate;
use App\Models\MessageTemplateVersion;
use App\Models\User;
use App\Support\History\History;
use App\Support\Templates\TemplateTokens;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CreateTemplateDraft extends Action
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function handle(MessageTemplate $template, string $subject, array $body, User $actor): MessageTemplateVersion
    {
        return $this->transaction(function () use ($template, $subject, $body, $actor): MessageTemplateVersion {
            if ($template->openDraft() instanceof MessageTemplateVersion) {
                throw new HttpException(409, 'This template already has a draft.');
            }

            $version = MessageTemplateVersion::query()->create([
                'template_id' => $template->id,
                'version' => ((int) $template->versions()->max('version')) + 1,
                'subject' => $subject,
                'body' => $body,
                'variables' => TemplateTokens::extract($subject, $body),
                'published' => false,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            History::record(
                $template,
                'template.draft_created',
                after: [
                    'version' => $version->version,
                    'subject' => $subject,
                ],
                actor: $actor,
            );

            return $version;
        });
    }
}
