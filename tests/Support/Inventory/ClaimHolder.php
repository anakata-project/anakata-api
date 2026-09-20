<?php

declare(strict_types=1);

namespace Tests\Support\Inventory;

use App\Models\CabinClaim;
use App\Models\ChangeHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string $reference
 * @property string $name
 */
class ClaimHolder extends Model
{
    protected $table = 'claim_holders';

    /**
     * @var list<string>
     */
    protected $fillable = ['reference', 'name'];

    public function historyLabel(): string
    {
        return $this->reference;
    }

    /**
     * @return MorphMany<CabinClaim, $this>
     */
    public function claims(): MorphMany
    {
        return $this->morphMany(CabinClaim::class, 'holder');
    }

    /**
     * @return MorphMany<ChangeHistory, $this>
     */
    public function history(): MorphMany
    {
        return $this->morphMany(ChangeHistory::class, 'subject');
    }
}
