<?php

declare(strict_types=1);

namespace App\Services\Documents;

final class DocumentFonts
{
    public static function embed(string $html): string
    {
        return str_replace('/* DOCUMENT_FONTS */', self::faces(), $html);
    }

    private static function faces(): string
    {
        $faces = [];

        foreach ([
            'Oswald' => resource_path('fonts/documents/Oswald/Oswald-Regular.ttf'),
            'Archivo' => resource_path('fonts/documents/Archivo/Archivo-Regular.ttf'),
            'IBM Plex Mono' => resource_path('fonts/documents/IBMPlexMono/IBMPlexMono-Regular.ttf'),
        ] as $family => $path) {
            $bytes = file_get_contents($path);

            if ($bytes === false) {
                continue;
            }

            $faces[] = '@font-face { font-family: "'.$family.'"; src: url(data:font/ttf;base64,'.base64_encode($bytes).") format('truetype'); font-weight: normal; font-style: normal; }";
        }

        return implode("\n", $faces);
    }
}
