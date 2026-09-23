<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertNotificationStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $report_run_id
 * @property int $user_id
 * @property AlertNotificationStatus $status
 * @property string|null $error
 * @property int $attempts
 * @property Carbon|null $sent_at
 * @property-read ReportRun $run
 * @property-read User $user
 */
#[Fillable([
    'report_run_id',
    'user_id',
    'status',
    'error',
    'attempts',
    'sent_at',
])]
class ReportRunNotification extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => AlertNotificationStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ReportRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ReportRun::class, 'report_run_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
