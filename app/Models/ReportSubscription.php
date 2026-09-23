<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportCadence;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $definition_key
 * @property ReportCadence $cadence
 * @property string $send_at
 * @property int|null $weekday
 * @property int|null $day_of_month
 * @property array<string, mixed> $parameters
 * @property bool $active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'definition_key',
    'cadence',
    'send_at',
    'weekday',
    'day_of_month',
    'parameters',
    'active',
])]
class ReportSubscription extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'cadence' => ReportCadence::class,
            'weekday' => 'integer',
            'day_of_month' => 'integer',
            'parameters' => 'array',
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ReportRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(ReportRun::class, 'subscription_id');
    }

    public function historyLabel(): string
    {
        return $this->definition_key.' '.$this->cadence->value;
    }

    public function clock(): string
    {
        return substr($this->send_at, 0, 5);
    }
}
