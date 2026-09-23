<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\AutomationKind;
use App\Enums\JourneyStepAction;
use App\Enums\TemplateVariable;
use App\Models\Journey;
use App\Models\MessageTemplate;
use App\Models\MessageTemplateVersion;
use App\Models\User;
use App\Support\History\History;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PublishTemplateVersion extends Action
{
    public function handle(MessageTemplate $template, MessageTemplateVersion $version, string $approvalReference, User $actor): MessageTemplateVersion
    {
        return $this->transaction(function () use ($template, $version, $approvalReference, $actor): MessageTemplateVersion {
            if ($version->template_id !== $template->id) {
                throw new HttpException(404, 'That template version does not exist.');
            }

            if ($version->published) {
                throw new HttpException(422, 'This version is already published.');
            }

            $reference = trim($approvalReference);

            if ($reference === '') {
                throw new HttpException(422, 'An approval reference is required.');
            }

            $this->guardTokens($version);
            $this->guardKind($template, $version);

            $version->markPublished($actor, $reference);

            History::record(
                $template,
                'template.published',
                after: ['version' => $version->version],
                reason: $reference,
                actor: $actor,
            );

            return $version->fresh() ?? $version;
        });
    }

    private function guardTokens(MessageTemplateVersion $version): void
    {
        foreach ($version->variables as $token) {
            if (TemplateVariable::tryFrom($token) === null) {
                throw new HttpException(422, 'Unknown template variable {{'.$token.'}}.');
            }
        }
    }

    private function guardKind(MessageTemplate $template, MessageTemplateVersion $version): void
    {
        $unsubscribe = in_array(TemplateVariable::UnsubscribeLink->value, $version->variables, true);

        if ($template->kind === AutomationKind::Marketing && ! $unsubscribe) {
            throw new HttpException(422, 'A marketing template must include {{unsubscribe_link}}.');
        }

        if ($template->kind === AutomationKind::Transactional && $unsubscribe) {
            throw new HttpException(422, 'A transactional template must not include {{unsubscribe_link}}.');
        }

        $journeys = Journey::query()
            ->whereHas('steps', function ($query) use ($template): void {
                $query->where('template_key', $template->key)
                    ->where('action', JourneyStepAction::Send);
            })
            ->get();

        foreach ($journeys as $journey) {
            if ($journey->kind !== $template->kind) {
                throw new HttpException(422, 'This template kind does not match its journey.');
            }
        }
    }
}
