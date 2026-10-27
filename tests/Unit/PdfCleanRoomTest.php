<?php

namespace Tests\Unit;

use App\Support\Pdf\Pdf;
use Tests\TestCase;

class PdfCleanRoomTest extends TestCase
{
    public function test_pdf_load_html_produces_valid_pdf_stream(): void
    {
        $html = '<html><head><meta charset="utf-8"></head><body><h1>فاکتور فروش دخـل</h1><p>تست خروجی پی‌دی‌اف</p></body></html>';
        $pdf = Pdf::loadHtml($html, ['format' => 'A4', 'orientation' => 'P']);

        $output = $pdf->output();

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF-', $output);
    }

    public function test_pdf_download_and_stream_responses(): void
    {
        $html = '<div>Report Content</div>';
        $doc = Pdf::loadHtml($html);

        $streamResponse = $doc->stream('test.pdf');
        $this->assertSame(200, $streamResponse->getStatusCode());
        $this->assertSame('application/pdf', $streamResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('inline; filename="test.pdf"', $streamResponse->headers->get('Content-Disposition'));

        $downloadResponse = $doc->download('download.pdf');
        $this->assertSame(200, $downloadResponse->getStatusCode());
        $this->assertSame('application/pdf', $downloadResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="download.pdf"', $downloadResponse->headers->get('Content-Disposition'));
    }
}
