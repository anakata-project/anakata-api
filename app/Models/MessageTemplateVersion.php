<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $template_id
 * @property int $version
 * @property string $subject
 * @property array<string, mixed> $body
 * @property list<string> $variables
 * @property bool $published
 * @property int|null $published_by
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $published_at
 * @property string|null $approval_reference
 * @property-read MessageTemplate $template
 */
#[Fillable([
    'template_id',
    'version',
    'subject',
    'body',
    'variables',
    'published',
    'published_by',
    'published_at',
    'approval_reference',
])]
class MessageTemplateVersion extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    private bool $allowPublish = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'body' => 'array',
            'variables' => 'array',
            'published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MessageTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function markPublished(User $actor, string $approvalReference): void
    {
        if ($this->published) {
            throw new LogicException('Message template versions cannot be updated.');
        }

        $this->allowPublish = true;
        $this->published = true;
        $this->published_by = $actor->id;
        $this->published_at = now();
        $this->approval_reference = $approvalReference;
        $this->updated_by = $actor->id;
        $this->save();
        $this->allowPublish = false;
    }

    public function save(array $options = []): bool
    {
        if ($this->exists && ! $this->allowPublish) {
            throw new LogicException('Message template versions cannot be updated.');
        }

        if ($this->exists && $this->getOriginal('published')) {
            throw new LogicException('Message template versions cannot be updated.');
        }

        if ($this->exists) {
            $allowed = [
                'published',
                'published_by',
                'published_at',
                'approval_reference',
                'updated_by',
                'updated_at',
            ];

            foreach (array_keys($this->getDirty()) as $key) {
                if (! in_array($key, $allowed, true)) {
                    throw new LogicException('Message template versions cannot be updated.');
                }
            }
        }

        return parent::save($options);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Message template versions cannot be updated.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Message template versions cannot be deleted.');
    }
}
