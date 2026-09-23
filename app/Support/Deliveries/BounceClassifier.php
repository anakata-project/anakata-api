<?php

declare(strict_types=1);

namespace App\Support\Deliveries;

use Throwable;

final class BounceClassifier
{
    public static function isHard(Throwable $exception): bool
    {
        $current = $exception;

        while ($current instanceof Throwable) {
            if (self::messageIsHard($current->getMessage())) {
                return true;
            }

            $current = $current->getPrevious();
        }

        return false;
    }

    private static function messageIsHard(string $message): bool
    {
        $message = strtolower($message);

        if (str_contains($message, '5.1.1')) {
            return true;
        }

        foreach ([
            'user unknown',
            'user-unknown',
            'mailbox not found',
            'recipient rejected',
            'does not exist',
        ] as $phrase) {
            if (str_contains($message, $phrase)) {
                return true;
            }
        }

        return false;
    }
}
