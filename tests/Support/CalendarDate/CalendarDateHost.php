<?php

declare(strict_types=1);

namespace Tests\Support\CalendarDate;

use App\Casts\CalendarDate;
use App\Models\Concerns\SerializesDatesAsUtc;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CarbonImmutable $day
 */
class CalendarDateHost extends Model
{
    use SerializesDatesAsUtc;

    protected $table = 'calendar_date_hosts';

    /**
     * @var list<string>
     */
    protected $fillable = ['day'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => CalendarDate::class,
        ];
    }
}
