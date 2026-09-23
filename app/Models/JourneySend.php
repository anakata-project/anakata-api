<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $journey_enrolment_id
 * @property int $journey_step_id
 * @property string $template_key
 * @property int|null $template_version
 * @property string|null $catalogue_key
 * @property int|null $delivery_id
 * @property Carbon $sent_at
 * @property-read JourneyEnrolment $enrolment
 * @property-read JourneyStep $step
 * @property-read Delivery|null $delivery
 */
#[Fillable([
    'journey_enrolment_id',
    'journey_step_id',
    'template_key',
    'template_version',
    'catalogue_key',
    'delivery_id',
    'sent_at',
])]
class JourneySend extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_version' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<JourneyEnrolment, $this>
     */
    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(JourneyEnrolment::class, 'journey_enrolment_id');
    }

    /**
     * @return BelongsTo<JourneyStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(JourneyStep::class, 'journey_step_id');
    }

    /**
     * @return BelongsTo<Delivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
