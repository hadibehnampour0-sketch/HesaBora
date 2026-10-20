<?php

namespace App\Services\AiVision\Parsers;

use App\Services\AiVision\Contracts\InvoiceVisionParserInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiVisionInvoiceParser implements InvoiceVisionParserInterface
{
    public function __construct(
        protected DeterministicMockVisionParser $fallbackParser
    ) {}

    public function parseInvoiceFile(string $absolutePath, string $mimeType): array
    {
        $apiKey = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');

        if (empty($apiKey) || ! file_exists($absolutePath)) {
            return $this->fallbackParser->parseInvoiceFile($absolutePath, $mimeType);
        }

        try {
            $imageData = base64_encode(file_get_contents($absolutePath));

            $prompt = <<<'EOT'
You are an expert Iranian accounting document parser. Extract all data from this paper invoice or receipt into a strict JSON object with these keys:
{
  "vendor": {
    "name": string,
    "national_code": string or null,
    "phone": string or null,
    "address": string or null
  },
  "invoice_number": string or null,
  "invoice_date": "YYYY-MM-DD" or standard date string,
  "items": [
    {
      "name": string,
      "quantity": float,
      "unit_price": integer (in Rials),
      "total_price": integer (in Rials),
      "vat_amount": integer,
      "discount": integer
    }
  ],
  "subtotal": integer,
  "vat_amount": integer,
  "total_amount": integer,
  "confidence_score": float between 0.50 and 1.00,
  "notes": string
}
Do not return any markdown wraps, only raw JSON.
EOT;

            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";

            $response = Http::timeout(30)->post($endpoint, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $imageData,
                                ],
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                ],
            ]);

            if ($response->successful()) {
                $body = $response->json();
                $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($text) {
                    $parsed = json_decode($text, true);
                    if (is_array($parsed) && isset($parsed['items'])) {
                        return $parsed;
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning('Gemini vision invoice parsing failed, using deterministic fallback: '.$e->getMessage());
        }

        return $this->fallbackParser->parseInvoiceFile($absolutePath, $mimeType);
    }
}
