<?php

declare(strict_types=1);

namespace App\Support\Mail;

final class QuotedReply
{
    public static function strip(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $kept = [];

        foreach (explode("\n", $text) as $line) {
            if (self::isCut($line)) {
                break;
            }

            if (preg_match('/^\s*>/', $line) === 1) {
                continue;
            }

            $kept[] = $line;
        }

        return trim(implode("\n", $kept));
    }

    public static function fromHtml(string $html): string
    {
        $withBreaks = preg_replace('/<\s*br\s*\/?>/i', "\n", $html) ?? $html;
        $withBreaks = preg_replace('/<\/p>/i', "\n", $withBreaks) ?? $withBreaks;
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($text);
    }

    public static function htmlFromText(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<p>'.nl2br($escaped, false).'</p>';
    }

    private static function isCut(string $line): bool
    {
        $trimmed = trim($line);

        if (preg_match('/^On .+ wrote:\s*$/i', $trimmed) === 1) {
            return true;
        }

        if (preg_match('/^-{5}\s*Original Message\s*-{5}\s*$/i', $trimmed) === 1) {
            return true;
        }

        return preg_match('/^--\s*$/', $trimmed) === 1;
    }
}
