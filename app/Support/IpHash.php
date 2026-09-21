<?php

declare(strict_types=1);

namespace App\Support;

final class IpHash
{
    public static function of(?string $ip): string
    {
        return hash_hmac('sha256', trim((string) $ip), (string) config('app.key'));
    }
}
