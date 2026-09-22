<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConsentCapturePoint;
use App\Enums\ConsentPurpose;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $contact_id
 * @property ConsentPurpose $purpose
 * @property bool $granted
 * @property string $version
 * @property Carbon $captured_at
 * @property string|null $ip
 * @property ConsentCapturePoint $capture_point
 * @property int|null $recorded_by
 * @property string|null $how_obtained
 * @property int|null $source_consent_id
 * @property string|null $session_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Contact $contact
 * @property-read User|null $recordedBy
 */
class ContactConsent extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => ConsentPurpose::class,
            'granted' => 'boolean',
            'captured_at' => 'datetime',
            'capture_point' => ConsentCapturePoint::class,
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
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
        return $this->purpose->label();
    }
}
