<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property int|null $offer_id
 * @property string|null $utm_campaign
 * @property string|null $audience
 * @property int $media_spend
 * @property CampaignStatus $status
 * @property int|null $owner_id
 * @property-read Offer|null $offer
 * @property-read User|null $owner
 */
#[Fillable([
    'name',
    'offer_id',
    'utm_campaign',
    'audience',
    'media_spend',
    'status',
    'owner_id',
])]
class Campaign extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_spend' => 'integer',
            'status' => CampaignStatus::class,
        ];
    }

    public function setUtmCampaignAttribute(?string $value): void
    {
        $trimmed = $value === null ? '' : strtolower(trim($value));
        $this->attributes['utm_campaign'] = $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function historyLabel(): string
    {
        return $this->name;
    }
}
