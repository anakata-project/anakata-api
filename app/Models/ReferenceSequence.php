<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Infrastructure counter. No audit columns and no history.
 *
 * @property int $id
 * @property string $scope
 * @property int $last_value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ReferenceSequence extends Model
{
    use SerializesDatesAsUtc;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'scope',
        'last_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_value' => 'integer',
        ];
    }
}
