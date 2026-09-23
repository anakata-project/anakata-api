<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Carbon\CarbonImmutable;

final class InboundMessageId
{
    public static function canonical(?string $header, string $from, CarbonImmutable $sentAt, string $subject, string $body): string
    {
        $normalised = self::normalise($header);

        if ($normalised !== null) {
            return $normalised;
        }

        return 'missing:'.hash('sha256', implode('|', [
            strtolower(trim($from)),
            $sentAt->utc()->format('Y-m-d\TH:i:s.v\Z'),
            $subject,
            $body,
        ]));
    }

    public static function normalise(?string $header): ?string
    {
        if ($header === null) {
            return null;
        }

        $header = trim($header);

        if ($header === '') {
            return null;
        }

        if (str_starts_with($header, 'missing:')) {
            return $header;
        }

        if (! str_starts_with($header, '<') || ! str_ends_with($header, '>')) {
            $header = '<'.trim($header, '<>').'>';
        }

        return $header;
    }

    /**
     * @return list<string>
     */
    public static function ids(?string $header): array
    {
        if ($header === null || trim($header) === '') {
            return [];
        }

        preg_match_all('/<[^>]+>/', $header, $matches);
        $raw = $matches[0] !== [] ? $matches[0] : preg_split('/\s+/', trim($header));
        $ids = [];

        foreach (is_array($raw) ? $raw : [] as $item) {
            $id = self::normalise($item);

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
