<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\DocumentKind;

final class DocumentHtml
{
    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function render(DocumentKind $kind, array $snapshot, bool $inlineFonts = false): string
    {
        $html = view(DocumentView::name($kind), ['snapshot' => $snapshot])->render();

        if (! $inlineFonts) {
            return $html;
        }

        return str_replace('/* DOCUMENT_FONTS */', self::fontFaceCss(), $html);
    }

    private static function fontFaceCss(): string
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
