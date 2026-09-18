<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\DB;

abstract class Action
{
    /**
     * @param  callable(): mixed  $callback
     */
    final protected function transaction(callable $callback): mixed
    {
        $result = null;

        DB::transaction(function () use ($callback, &$result): void {
            $result = $callback();
        });

        return $result;
    }
}
