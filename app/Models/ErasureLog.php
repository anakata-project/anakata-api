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
 * @property int $contact_id
 * @property string $email_sha256
 * @property Carbon $erased_at
 * @property int|null $erased_by
 */
#[Fillable([
    'contact_id',
    'email_sha256',
    'erased_at',
    'erased_by',
])]
class ErasureLog extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    protected $table = 'erasure_log';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'erased_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function historyLabel(): string
    {
        return 'Erasure '.$this->id;
    }
}
