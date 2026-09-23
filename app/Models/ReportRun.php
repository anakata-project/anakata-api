<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\CalendarDate;
use App\Enums\ReportRunStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $definition_key
 * @property array<string, mixed> $parameters
 * @property CarbonImmutable $window_from
 * @property CarbonImmutable $window_to
 * @property int|null $requested_by
 * @property int|null $subscription_id
 * @property ReportRunStatus $status
 * @property string|null $error
 * @property int $rows
 * @property Carbon|null $generated_at
 * @property string|null $csv_path
 * @property string|null $xlsx_path
 * @property string|null $pdf_path
 * @property Carbon|null $purged_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $requestedBy
 */
#[Fillable([
    'definition_key',
    'parameters',
    'window_from',
    'window_to',
    'requested_by',
    'subscription_id',
    'status',
    'error',
    'rows',
    'generated_at',
    'csv_path',
    'xlsx_path',
    'pdf_path',
    'purged_at',
])]
class ReportRun extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'window_from' => CalendarDate::class,
            'window_to' => CalendarDate::class,
            'status' => ReportRunStatus::class,
            'rows' => 'integer',
            'generated_at' => 'datetime',
            'purged_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<ReportSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ReportSubscription::class, 'subscription_id');
    }

    /**
     * @return HasMany<ReportRunNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(ReportRunNotification::class);
    }

    public function historyLabel(): string
    {
        return $this->definition_key.' '.$this->window_from->toDateString().'–'.$this->window_to->toDateString();
    }
}
