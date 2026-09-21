<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\CalendarDate;
use App\Enums\CharterEnquirySource;
use App\Enums\CharterEnquiryStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Carbon\CarbonImmutable;
use Database\Factories\CharterEnquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property CarbonImmutable|null $preferred_from
 * @property CarbonImmutable|null $preferred_to
 * @property int|null $departure_id
 * @property int $guests
 * @property int $contact_id
 * @property string $message
 * @property CharterEnquirySource $source
 * @property CharterEnquiryStatus $status
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Departure|null $departure
 * @property-read Contact $contact
 */
#[Fillable([
    'preferred_from',
    'preferred_to',
    'departure_id',
    'guests',
    'contact_id',
    'message',
    'source',
    'status',
])]
class CharterEnquiry extends Model
{
    /** @use HasFactory<CharterEnquiryFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_from' => CalendarDate::class,
            'preferred_to' => CalendarDate::class,
            'guests' => 'integer',
            'source' => CharterEnquirySource::class,
            'status' => CharterEnquiryStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Departure, $this>
     */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return MorphMany<ChangeHistory, $this>
     */
    public function history(): MorphMany
    {
        return $this->morphMany(ChangeHistory::class, 'subject');
    }

    public function historyLabel(): string
    {
        $this->loadMissing('contact');

        return $this->contact->name;
    }
}
