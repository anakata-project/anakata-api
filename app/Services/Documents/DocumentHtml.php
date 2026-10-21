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

        if (! $inlineFonts || ! str_contains($html, '/* DOCUMENT_FONTS */')) {
            return $html;
        }

        return str_replace(
            ['/* DOCUMENT_FONTS */', '</style>', '<body>'],
            [self::fontFaceCss(), self::screenCss().'</style>', '<body class="is-screen">'],
            $html,
        );
    }

    private static function screenCss(): string
    {
        return <<<'CSS'
        body.is-screen { background: #E4E1D2; }
        body.is-screen .paper { box-sizing: border-box; width: 100%; max-width: 100%; margin: 0; background: #FAF9F0; padding: 28px 32px 40px; }
        body.is-screen table { width: 100%; max-width: 100%; }
        body.is-screen td,
        body.is-screen th,
        body.is-screen .dtitle,
        body.is-screen .dsub,
        body.is-screen .dnote { overflow-wrap: anywhere; }
        @media print {
            body.is-screen { background: #fff; }
            body.is-screen .paper { max-width: none; padding: 0; background: #fff; }
        }

        CSS;
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
