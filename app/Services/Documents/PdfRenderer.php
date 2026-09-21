<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Exceptions\PdfRenderException;
use Dompdf\Dompdf;
use Dompdf\Options;
use Throwable;

final class PdfRenderer
{
    public function render(string $html): string
    {
        $fontDir = storage_path('app/documents-fonts');

        if (! is_dir($fontDir) && ! mkdir($fontDir, 0755, true) && ! is_dir($fontDir)) {
            throw new PdfRenderException('Could not create the PDF font cache directory.');
        }

        try {
            $options = new Options;
            $options->set('isRemoteEnabled', false);
            $options->set('isPhpEnabled', false);
            $options->set('isJavascriptEnabled', false);
            $options->set('defaultFont', 'Archivo');
            $options->set('chroot', [
                resource_path('fonts/documents'),
                resource_path('views/documents'),
                $fontDir,
            ]);
            $options->set('fontDir', $fontDir);
            $options->set('fontCache', $fontDir);
            $options->set('tempDir', $fontDir);

            $dompdf = new Dompdf($options);
            $this->registerFonts($dompdf);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $output = $dompdf->output();
        } catch (PdfRenderException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new PdfRenderException('The PDF renderer failed: '.$e->getMessage(), previous: $e);
        }

        if ($output === '' || ! str_starts_with($output, '%PDF')) {
            throw new PdfRenderException('The PDF renderer returned empty or invalid output.');
        }

        return $output;
    }

    private function registerFonts(Dompdf $dompdf): void
    {
        $metrics = $dompdf->getFontMetrics();

        foreach ([
            'Archivo' => resource_path('fonts/documents/Archivo/Archivo-Regular.ttf'),
            'Oswald' => resource_path('fonts/documents/Oswald/Oswald-Regular.ttf'),
            'IBM Plex Mono' => resource_path('fonts/documents/IBMPlexMono/IBMPlexMono-Regular.ttf'),
        ] as $family => $path) {
            if (! is_file($path)) {
                throw new PdfRenderException('Bundled font missing: '.$family);
            }

            $metrics->registerFont([
                'family' => $family,
                'style' => 'normal',
                'weight' => 'normal',
            ], $path);
        }
    }
}
