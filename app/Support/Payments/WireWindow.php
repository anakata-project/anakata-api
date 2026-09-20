<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Config\CurrentConfig;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final class WireWindow
{
    public static function hours(): int
    {
        return app(CurrentConfig::class)->businessRules()->payments->wireWindowHours;
    }

    public static function endsAt(DateTimeInterface $startedAt): CarbonImmutable
    {
        return CarbonImmutable::instance($startedAt)->addHours(self::hours());
    }

    public static function endsAtFor(Payment $payment): ?CarbonImmutable
    {
        if ($payment->status !== PaymentStatus::AwaitingWire) {
            return null;
        }

        return self::endsAt($payment->created_at);
    }
}
