<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConfigKind;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use App\Support\Config\ConfigDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $version
 * @property array<string, mixed> $document
 * @property list<array{path: string, label: string, from: mixed, to: mixed}> $changes
 * @property string|null $approval_reference
 * @property Carbon $published_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
abstract class ConfigVersion extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'version',
        'document',
        'changes',
        'approval_reference',
        'published_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'document' => 'array',
            'changes' => 'array',
            'published_at' => 'datetime',
        ];
    }

    abstract public static function configKind(): ConfigKind;

    public function asDocument(): ConfigDocument
    {
        $class = static::configKind()->documentClass();

        return $class::fromArray($this->document ?? []);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function historyLabel(): string
    {
        return 'v'.$this->version;
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Configuration versions cannot be updated.');
        }

        return parent::save($options);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Configuration versions cannot be updated.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Configuration versions cannot be deleted.');
    }
}
