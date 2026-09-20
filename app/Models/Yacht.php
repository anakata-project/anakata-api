<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Database\Factories\YachtFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Cabin> $cabins
 */
#[Fillable(['code', 'name'])]
class Yacht extends Model
{
    /** @use HasFactory<YachtFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc;

    /**
     * @return HasMany<Cabin, $this>
     */
    public function cabins(): HasMany
    {
        return $this->hasMany(Cabin::class)->orderBy('sort');
    }
}
