<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class HealthChecker
{
    public function database(): void
    {
        DB::select('select 1');
    }

    public function redis(): void
    {
        Redis::connection()->ping();
    }

    public function queue(): void
    {
        Queue::connection()->size();
    }
}
