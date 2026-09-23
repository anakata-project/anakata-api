<?php

declare(strict_types=1);

namespace App\Support\Templates;

final class TemplateTokens
{
    /**
     * @param  array<string, mixed>  $body
     * @return list<string>
     */
    public static function extract(string $subject, array $body): array
    {
        $texts = [$subject];

        foreach (self::strings($body['paragraphs'] ?? []) as $paragraph) {
            $texts[] = $paragraph;
        }

        foreach (self::strings($body['list'] ?? []) as $item) {
            $texts[] = $item;
        }

        $cta = $body['cta'] ?? null;

        if (is_array($cta)) {
            if (is_string($cta['label'] ?? null)) {
                $texts[] = $cta['label'];
            }

            if (is_string($cta['link_key'] ?? null) && $cta['link_key'] !== '') {
                $texts[] = '{{'.$cta['link_key'].'}}';
            }
        }

        $found = [];

        foreach ($texts as $text) {
            $count = preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/', $text, $matches);

            if ($count === false || $count < 1) {
                continue;
            }

            foreach ($matches[1] as $token) {
                $found[$token] = true;
            }
        }

        $tokens = array_keys($found);
        sort($tokens);

        return $tokens;
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $strings = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                $strings[] = $value;
            }
        }

        return $strings;
    }
}
