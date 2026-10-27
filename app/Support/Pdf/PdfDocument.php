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

    public function __construct(
        private string $html,
        private array $config = [],
    ) {
        $this->parseConfig($config);
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
        if (class_exists(DomPdf::class)) {
            $domPdf = DomPdf::loadHTML($this->html);
            $domPdf->setPaper($this->format, $this->orientation);

            return $domPdf->output();
        }

        // Lightweight pure-PHP PDF fallback stream for testing/mocking environments
        return $this->generateFallbackPdf();
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
