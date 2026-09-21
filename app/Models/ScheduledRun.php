<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScheduledRunOutcome;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $command
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property ScheduledRunOutcome $outcome
 * @property int|null $exit_code
 * @property string|null $output
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'command',
    'started_at',
    'finished_at',
    'outcome',
    'exit_code',
    'output',
])]
class ScheduledRun extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'outcome' => ScheduledRunOutcome::class,
            'exit_code' => 'integer',
        ];
    }
}
