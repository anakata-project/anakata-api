<?php

declare(strict_types=1);

namespace App\Actions\Crm;

use App\Actions\Action;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;
use App\Support\History\History;
use Illuminate\Validation\ValidationException;

final class SaveCampaign extends Action
{
    /**
     * @param  array{name: string, offer_id: int|null, utm_campaign: string|null, audience: string|null, media_spend: int}  $data
     */
    public function create(array $data, User $actor): Campaign
    {
        $this->assertBound($data['offer_id'], $data['utm_campaign']);

        return $this->transaction(function () use ($data, $actor): Campaign {
            $campaign = Campaign::query()->create([
                'name' => $data['name'],
                'offer_id' => $data['offer_id'],
                'utm_campaign' => $data['utm_campaign'],
                'audience' => $data['audience'],
                'media_spend' => $data['media_spend'],
                'status' => CampaignStatus::Active,
                'owner_id' => $actor->id,
            ]);

            History::record($campaign, 'campaign.created', after: [
                'name' => $campaign->name,
                'offer_id' => $campaign->offer_id,
                'utm_campaign' => $campaign->utm_campaign,
                'media_spend' => $campaign->media_spend,
            ], actor: $actor);

            return $campaign->refresh();
        });
    }

    /**
     * @param  array{name?: string, offer_id?: int|null, utm_campaign?: string|null, audience?: string|null, media_spend?: int}  $data
     */
    public function update(Campaign $campaign, array $data, User $actor): Campaign
    {
        $offerId = array_key_exists('offer_id', $data) ? $data['offer_id'] : $campaign->offer_id;
        $utm = array_key_exists('utm_campaign', $data) ? $data['utm_campaign'] : $campaign->utm_campaign;
        $this->assertBound($offerId, $utm);

        return $this->transaction(function () use ($campaign, $data, $actor): Campaign {
            $before = [
                'name' => $campaign->name,
                'offer_id' => $campaign->offer_id,
                'utm_campaign' => $campaign->utm_campaign,
                'audience' => $campaign->audience,
                'media_spend' => $campaign->media_spend,
            ];

            $campaign->fill($data);
            $campaign->save();
            $campaign->refresh();

            $after = [
                'name' => $campaign->name,
                'offer_id' => $campaign->offer_id,
                'utm_campaign' => $campaign->utm_campaign,
                'audience' => $campaign->audience,
                'media_spend' => $campaign->media_spend,
            ];

            if ($before !== $after) {
                History::record($campaign, 'campaign.updated', before: $before, after: $after, actor: $actor);
            }

            return $campaign;
        });
    }

    private function assertBound(?int $offerId, ?string $utm): void
    {
        $key = $utm === null ? '' : strtolower(trim($utm));

        if ($offerId === null && $key === '') {
            throw ValidationException::withMessages([
                'utm_campaign' => ['A campaign needs an offer or a UTM campaign key.'],
            ]);
        }
    }
}
