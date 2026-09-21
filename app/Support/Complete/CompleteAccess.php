<?php

declare(strict_types=1);

namespace App\Support\Complete;

final class CompleteAccess
{
    public const NOT_FOUND = 'Not found.';

    public const ACTOR_LABEL = 'Guest (self-service)';

    public static function abortNotFound(): never
    {
        abort(404, self::NOT_FOUND);
    }
}
