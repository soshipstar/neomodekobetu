<?php

namespace App\Services;

use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class PuppeteerPdfService
{
    /**
     * Render a Blade view to PDF using Puppeteer (Chromium headless).
     *
     * @param  string  $view   Blade view name (e.g., 'pdf.support-plan')
     * @param  array   $data   Data passed to the view
     * @param  string  $filename  Download filename
     * @param  string  $format   Paper format (A4, Letter, etc.)
     * @param  bool    $landscape
     * @return Response
     */
    public static function download(string $view, array $data, string $filename, string $format = 'A4', bool $landscape = false): Response
    {
        $html = View::make($view, $data)->render();

        $pdfBinary = self::htmlToPdf($html, $format, $landscape);

        return new Response($pdfBinary, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Convert raw HTML to PDF binary.
     */
    public static function htmlToPdf(string $html, string $format = 'A4', bool $landscape = false): string
    {
        $tmpHtml = tempnam(sys_get_temp_dir(), 'pdf_') . '.html';
        $tmpPdf  = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';

        file_put_contents($tmpHtml, $html);

        $script = base_path('scripts/html-to-pdf.js');
        $args   = escapeshellarg($tmpHtml) . ' ' . escapeshellarg($tmpPdf) . ' --format=' . escapeshellarg($format);

        if ($landscape) {
            $args .= ' --landscape';
        }

        $command = 'node ' . escapeshellarg($script) . ' ' . $args . ' 2>&1';
        $output  = [];
        $code    = 0;

        exec($command, $output, $code);

        if ($code !== 0 || !file_exists($tmpPdf)) {
            @unlink($tmpHtml);
            @unlink($tmpPdf);
            $errorMsg = implode("\n", $output);
            throw new \RuntimeException("Puppeteer PDF generation failed (code {$code}): {$errorMsg}");
        }

        $pdf = file_get_contents($tmpPdf);

        @unlink($tmpHtml);
        @unlink($tmpPdf);

        return $pdf;
    }
}
