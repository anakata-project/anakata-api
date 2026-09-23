<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AutomationKind;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property AutomationKind $kind
 * @property-read Collection<int, MessageTemplateVersion> $versions
 */
#[Fillable([
    'key',
    'name',
    'kind',
])]
class MessageTemplate extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AutomationKind::class,
        ];
    }

    /**
     * @return HasMany<MessageTemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(MessageTemplateVersion::class, 'template_id');
    }

    public function publishedVersion(): ?MessageTemplateVersion
    {
        return $this->versions()->where('published', true)->orderByDesc('version')->first();
    }

    public function openDraft(): ?MessageTemplateVersion
    {
        return $this->versions()->where('published', false)->orderByDesc('version')->first();
    }

    public function historyLabel(): string
    {
        return $this->key;
    }

    /**
     * @return MorphMany<ChangeHistory, $this>
     */
    public function history(): MorphMany
    {
        return $this->morphMany(ChangeHistory::class, 'subject');
    }
}
