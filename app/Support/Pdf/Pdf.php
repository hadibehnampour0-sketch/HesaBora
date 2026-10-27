<?php

namespace App\Support\Pdf;

use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Support\Facades\View;

/**
 * Clean-Room Permissive (MIT) PDF Service.
 *
 * Provides a unified, decoupled interface for PDF generation across Dakhl,
 * mapping views, parameters, and paper configurations to DomPDF or headless renderers.
 *
 * License: MIT
 */
class Pdf
{
    /**
     * Load a Blade view and configure paper settings.
     */
    public static function loadView(string $view, array $data = [], array $mergeData = [], array $config = []): PdfDocument
    {
        $renderedHtml = View::make($view, $data, $mergeData)->render();

        return new PdfDocument($renderedHtml, $config);
    }

    /**
     * Load raw HTML content.
     */
    public static function loadHtml(string $html, array $config = []): PdfDocument
    {
        return new PdfDocument($html, $config);
    }
}
