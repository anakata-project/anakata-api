<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConsentDocument;
use App\Enums\ConsentSource;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Database\Factories\ConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property ConsentDocument $document
 * @property string $version
 * @property Carbon $accepted_at
 * @property string|null $ip
 * @property ConsentSource $source
 * @property int|null $recorded_by
 * @property string|null $how_obtained
 * @property bool $withdrawn
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 * @property-read User|null $recordedBy
 */
#[Fillable([
    'booking_id',
    'document',
    'version',
    'accepted_at',
    'ip',
    'source',
    'recorded_by',
    'how_obtained',
    'withdrawn',
])]
class Consent extends Model
{
    /** @use HasFactory<ConsentFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document' => ConsentDocument::class,
            'source' => ConsentSource::class,
            'accepted_at' => 'datetime',
            'withdrawn' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function historyLabel(): string
    {
        return $this->document->label().' · '.$this->version;
    }
}
