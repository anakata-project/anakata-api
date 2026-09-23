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
 * @property int $message_template_version_id
 * @property int $user_id
 * @property int|null $contact_id
 * @property int|null $booking_id
 * @property AlertNotificationStatus $status
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property-read MessageTemplateVersion $version
 * @property-read User $user
 */
#[Fillable([
    'message_template_version_id',
    'user_id',
    'contact_id',
    'booking_id',
    'status',
    'error',
    'sent_at',
])]
class TemplateTestSend extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AlertNotificationStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MessageTemplateVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(MessageTemplateVersion::class, 'message_template_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
