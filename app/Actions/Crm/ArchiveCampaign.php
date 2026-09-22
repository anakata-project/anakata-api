<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;
use App\Support\History\History;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ArchiveCampaign extends Action
{
    public function handle(Campaign $campaign, User $actor): Campaign
    {
        if ($campaign->status === CampaignStatus::Archived) {
            throw new HttpException(422, 'This campaign is already archived.');
        }

        return $this->transaction(function () use ($campaign, $actor): Campaign {
            $campaign->forceFill(['status' => CampaignStatus::Archived])->save();

            History::record($campaign, 'campaign.archived', before: [
                'status' => CampaignStatus::Active->value,
            ], after: [
                'status' => CampaignStatus::Archived->value,
            ], actor: $actor);

            return $campaign->refresh();
        });
    }
}
