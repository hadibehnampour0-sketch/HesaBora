<?php

namespace App\Support\Pdf;

use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clean-Room PDF Document Wrapper.
 *
 * Encapsulates rendered HTML and delegates output generation to DomPDF
 * with fallback support for raw PDF byte streams.
 *
 * License: MIT
 */
class PdfDocument
{
    private string $format = 'a4';

    private string $orientation = 'portrait';

    private string $driver = 'dompdf';

    public function __construct(
        private string $html,
        private array $config = [],
    ) {
        $this->parseConfig($config);
        $this->driver = config('pdf.driver', 'dompdf');
    }

    private function parseConfig(array $config): void
    {
        if (isset($config['format'])) {
            $this->format = strtolower($config['format']);
        }

        if (isset($config['orientation'])) {
            $ori = strtoupper($config['orientation']);
            $this->orientation = ($ori === 'L' || $ori === 'LANDSCAPE') ? 'landscape' : 'portrait';
        }

        if (isset($config['driver'])) {
            $this->driver = strtolower($config['driver']);
        }
    }

    public function setDriver(string $driver): self
    {
        $this->driver = strtolower($driver);

        return $this;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function setPaper(string $format, string $orientation = 'portrait'): self
    {
        $this->format = strtolower($format);
        $this->orientation = strtolower($orientation);

        return $this;
    }

    /**
     * Generate and return the binary PDF content.
     */
    public function output(): string
    {
        if ($this->driver === 'chromium') {
            $chromiumOutput = $this->renderWithChromium();
            if ($chromiumOutput !== null) {
                return $chromiumOutput;
            }
        }

        if (class_exists(DomPdf::class)) {
            $domPdf = DomPdf::loadHTML($this->html);
            $domPdf->setPaper($this->format, $this->orientation);

            return $domPdf->output();
        }

        // Lightweight pure-PHP PDF fallback stream for testing/mocking environments
        return $this->generateFallbackPdf();
    }

    /**
     * Attempt to render PDF using headless Chromium (Edge/Chrome/Electron).
     */
    protected function renderWithChromium(): ?string
    {
        $binary = config('pdf.chromium_binary')
            ?: (PHP_OS_FAMILY === 'Windows'
                ? 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe'
                : '/usr/bin/chromium');

        if (! file_exists($binary)) {
            return null;
        }

        $tmpHtml = tempnam(sys_get_temp_dir(), 'dakhl_pdf_').'.html';
        $tmpPdf = tempnam(sys_get_temp_dir(), 'dakhl_pdf_').'.pdf';

        file_put_contents($tmpHtml, $this->html);

        $cmd = sprintf(
            '"%s" --headless --disable-gpu --no-pdf-header-footer --print-to-pdf="%s" "%s" 2>&1',
            $binary,
            $tmpPdf,
            $tmpHtml
        );

        @exec($cmd, $output, $exitCode);

        $pdfContent = null;
        if (file_exists($tmpPdf) && filesize($tmpPdf) > 0) {
            $pdfContent = file_get_contents($tmpPdf);
            @unlink($tmpPdf);
        }

        @unlink($tmpHtml);

        return $pdfContent;
    }

    public function stream(string $filename = 'document.pdf'): Response
    {
        return response($this->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function download(string $filename = 'document.pdf'): Response
    {
        return response($this->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function generateFallbackPdf(): string
    {
        $cleanText = strip_tags($this->html);
        $content = 'BT /F1 12 Tf 50 750 Td ('.addcslashes(substr($cleanText, 0, 500), "\\()\r\n\t").') Tj ET';
        $streamLen = strlen($content);

        return "%PDF-1.4\n"
            ."1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            ."2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n"
            ."4 0 obj << /Length {$streamLen} >> stream\n{$content}\nendstream\nendobj\n"
            ."5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n"
            ."xref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000244 00000 n \n0000000306 00000 n \n"
            ."trailer << /Size 6 /Root 1 0 R >>\nstartxref\n387\n%%EOF";
    }
}
