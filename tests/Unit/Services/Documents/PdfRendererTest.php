<?php

declare(strict_types=1);

use App\Exceptions\PdfRenderException;
use App\Services\Documents\PdfRenderer;
use Tests\TestCase;

uses(TestCase::class);

test('the real renderer returns PDF bytes', function (): void {
    $html = view('documents.proof', [
        'snapshot' => [
            'title' => 'Proof',
            'subtitle' => 'Renderer',
            'line' => 'One line',
        ],
    ])->render();

    $bytes = app(PdfRenderer::class)->render($html);

    expect($bytes)->toStartWith('%PDF');
    expect(strlen($bytes))->toBeGreaterThan(100);
});

test('a render failure throws a typed exception', function (): void {
    $path = resource_path('fonts/documents/Archivo/Archivo-Regular.ttf');
    $backup = $path.'.missing';
    rename($path, $backup);

    try {
        expect(fn () => app(PdfRenderer::class)->render('<html><body>x</body></html>'))
            ->toThrow(PdfRenderException::class);
    } finally {
        rename($backup, $path);
    }
});
