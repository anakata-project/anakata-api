<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertNotificationStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Database\Factories\AlertNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $alert_id
 * @property int $user_id
 * @property AlertNotificationStatus $status
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property int $attempts
 * @property-read Alert $alert
 * @property-read User $user
 */
#[Fillable([
    'alert_id',
    'user_id',
    'status',
    'error',
    'sent_at',
    'attempts',
])]
class AlertNotification extends Model
{
    /** @use HasFactory<AlertNotificationFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AlertNotificationStatus::class,
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Alert, $this>
     */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
