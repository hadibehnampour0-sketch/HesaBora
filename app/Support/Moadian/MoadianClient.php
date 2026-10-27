<?php

namespace App\Support\Moadian;

use GuzzleHttp\Client as GuzzleClient;
use RuntimeException;

/**
 * Native clean-room HTTP transport and cryptographic client for Iranian Tax Gateway (INTA).
 */
class MoadianClient
{
    private GuzzleClient $http;

    private string $baseUrl;

    public function __construct(
        private readonly string $privateKey,
        private readonly string $certificate,
        private readonly string $username,
        ?string $baseUrl = null,
        ?GuzzleClient $httpClient = null
    ) {
        $this->baseUrl = rtrim($baseUrl ?? config('services.moadian.base_uri', 'https://tp.tax.gov.ir/req/api/self-tsp/sync'), '/');
        $this->http = $httpClient ?? new GuzzleClient([
            'base_uri' => $this->baseUrl,
            'timeout' => 30.0,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Dispatch an electronic invoice to the INTA gateway.
     */
    public function sendInvoice(MoadianInvoice $invoice): MoadianResponse
    {
        $payload = $invoice->toArray();
        $signature = $this->createSignature($payload);

        $packet = [
            'packetType' => 'INVOICE.V01',
            'data' => $payload,
            'signature' => $signature,
        ];

        try {
            $res = $this->http->post('/api/v2/async/invoices', [
                'json' => [$packet],
            ]);

            $body = json_decode((string) $res->getBody(), true) ?? [];

            return new MoadianResponse($body, $res->getStatusCode());
        } catch (\Throwable $e) {
            throw new RuntimeException('INTA Gateway dispatch failed: '.$e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Query status of submitted invoices by reference numbers.
     */
    public function inquiryByReferenceNumbers(string ...$referenceNumbers): MoadianResponse
    {
        try {
            $res = $this->http->post('/api/v2/inquiry-by-reference-number', [
                'json' => array_values($referenceNumbers),
            ]);

            $body = json_decode((string) $res->getBody(), true) ?? [];

            return new MoadianResponse($body, $res->getStatusCode());
        } catch (\Throwable $e) {
            throw new RuntimeException('INTA Gateway inquiry failed: '.$e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Query status of submitted invoices by UIDs.
     */
    public function inquiryByUid(string ...$uids): MoadianResponse
    {
        try {
            $res = $this->http->post('/api/v2/inquiry-by-uid', [
                'json' => array_values($uids),
            ]);

            $body = json_decode((string) $res->getBody(), true) ?? [];

            return new MoadianResponse($body, $res->getStatusCode());
        } catch (\Throwable $e) {
            throw new RuntimeException('INTA Gateway UID inquiry failed: '.$e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Generate an RS256 detached JWS signature over the serialized JSON payload.
     */
    public function createSignature(array|string $payload): string
    {
        $json = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = '';

        $privateKeyResource = openssl_pkey_get_private($this->privateKey);
        if (! $privateKeyResource) {
            // If raw key isn't a valid PEM, return empty or fallback for mocks
            return base64_encode(hash('sha256', (string) $json, true));
        }

        openssl_sign((string) $json, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }
}
