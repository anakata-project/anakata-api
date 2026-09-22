<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        /* DOCUMENT_FONTS */
        @page { size: A4; margin: 12mm 13mm; }
        body { font-family: Archivo, sans-serif; font-size: 11px; line-height: 1.45; color: #202B26; background: #FAF9F0; margin: 0; }
        .paper { width: 100%; }
        .dh { width: 100%; border-collapse: collapse; border-bottom: 2px solid #202B26; margin-bottom: 12px; }
        .dh td { vertical-align: top; padding-bottom: 10px; }
        .dlogo { font-family: Oswald, sans-serif; letter-spacing: 0.35em; font-size: 18px; }
        .dtag { font-size: 10px; color: #6b6b5a; letter-spacing: 0.06em; }
        .dtitle { font-family: Oswald, sans-serif; letter-spacing: 0.16em; font-size: 13px; text-align: right; }
        .dsub { font-size: 10.5px; color: #585940; line-height: 1.6; text-align: right; }
        .dt { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .dt th, .dt td { padding: 3px 6px 3px 0; text-align: left; font-size: 10px; vertical-align: top; }
        .dt th { font-family: "IBM Plex Mono", monospace; font-size: 8px; letter-spacing: 0.08em; text-transform: uppercase; color: #6b6b5a; border-bottom: 1px solid #202B26; }
        tr.miss td { color: #C43D2E; }
        .dnote { font-size: 10.5px; color: #6b6b5a; margin: 8px 0; }
        .ref { font-size: 8px; color: #6b6b5a; }
    </style>
</head>
<body>
<div class="paper">
    @yield('body')
</div>
</body>
</html>
