<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SalesMaterialKind;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Database\Factories\SalesMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property string $title
 * @property SalesMaterialKind $kind
 * @property int|null $agency_id
 * @property int $version
 * @property string|null $file_path
 * @property string $mime
 * @property int $bytes
 * @property int $uploaded_by
 * @property bool $published
 * @property Carbon|null $purged_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Agency|null $agency
 * @property-read User|null $uploadedBy
 */
#[Fillable([
    'title',
    'kind',
    'agency_id',
    'version',
    'file_path',
    'mime',
    'bytes',
    'uploaded_by',
    'published',
    'purged_at',
])]
class SalesMaterial extends Model
{
    /** @use HasFactory<SalesMaterialFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc;

    /** @var list<string> */
    private const MUTABLE_COLUMNS = [
        'published',
        'file_path',
        'purged_at',
        'updated_at',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SalesMaterialKind::class,
            'agency_id' => 'integer',
            'version' => 'integer',
            'bytes' => 'integer',
            'uploaded_by' => 'integer',
            'published' => 'boolean',
            'purged_at' => 'datetime',
        ];
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            foreach (array_keys($this->getDirty()) as $column) {
                if (! in_array($column, self::MUTABLE_COLUMNS, true)) {
                    throw new LogicException('Sales material columns are immutable.');
                }
            }

            if ($this->isDirty('purged_at') && ($this->getOriginal('purged_at') !== null || $this->purged_at === null)) {
                throw new LogicException('Sales material purged_at can only be set once.');
            }
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new LogicException('Sales materials are append-only.');
    }

    /**
     * @param  Builder<SalesMaterial>  $query
     */
    public function scopeVisibleTo(Builder $query, Agency $agency): void
    {
        $query->where('published', true)
            ->whereNull('purged_at')
            ->where(function (Builder $inner) use ($agency): void {
                $inner->whereNull('agency_id')->orWhere('agency_id', $agency->id);
            });
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function historyLabel(): string
    {
        return $this->title.' v'.$this->version;
    }
}
