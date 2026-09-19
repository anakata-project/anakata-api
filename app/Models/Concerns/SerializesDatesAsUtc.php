<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Iso;
use DateTimeInterface;

trait SerializesDatesAsUtc
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Iso::utc($date);
    }
}
