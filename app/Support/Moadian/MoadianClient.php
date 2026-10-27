<?php

namespace App\Support\Moadian;

use Carbon\Carbon;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * Clean-Room Moadian API Client.
 *
 * Implements communication with the Iranian Tax Administration (INTA) e-invoicing endpoints:
 * - Server Info & Public Key retrieval
 * - Nonce token generation
 * - JWS (RS256) signature generation via Firebase JWT
 * - JWE (AES-256-GCM + RSA-OAEP) payload encryption
 * - Invoice submission (sendInvoice)
 * - Status inquiry (inquiryByReferenceNumbers)
 *
 * License: MIT
 */
class MoadianClient
{
    private Client $http;

    private ?string $serverPublicKey = null;

    private ?string $serverKeyId = null;

    public function __construct(
        private string $privateKey,
        private string $certificate,
        private string $username,
        private ?string $baseUri = null,
    ) {
        $this->baseUri ??= config('moadian.base_uri') ?? env('MOADIAN_BASE_URI', 'https://tp.tax.gov.ir/requestsmanager/api/v2/');
        $this->http = new Client([
            'base_uri' => rtrim($this->baseUri, '/').'/',
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 60,
        ]);
    }

    public function sendInvoice(MoadianInvoice $invoice): MoadianResponse
    {
        $this->ensureServerPublicKey();

        $jws = $this->signPayload($invoice->toArray());
        $jwe = $this->encryptPayload($jws);

        $body = [
            [
                'payload' => $jwe,
                'header' => [
                    'requestTraceId' => Uuid::uuid4()->toString(),
                    'fiscalId' => $this->username,
                ],
            ],
        ];

        $token = $this->generateToken();

        $response = $this->http->post('invoice', [
            'json' => $body,
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        $responseData = json_decode((string) $response->getBody(), true) ?? [];

        return new MoadianResponse($responseData, $response->getStatusCode());
    }

    public function inquiryByReferenceNumbers(string $referenceNumber, string $start = '', string $end = ''): MoadianResponse
    {
        $token = $this->generateToken();

        $query = [];
        if ($start !== '') {
            $query['start'] = $start;
        }
        if ($end !== '') {
            $query['end'] = $end;
        }

        $response = $this->http->get('inquiry/reference-number/'.$referenceNumber, [
            'query' => $query,
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        $responseData = json_decode((string) $response->getBody(), true) ?? [];

        return new MoadianResponse($responseData, $response->getStatusCode());
    }

    public function getServerInfo(): MoadianResponse
    {
        $response = $this->http->get('server-information');
        $data = json_decode((string) $response->getBody(), true) ?? [];

        return new MoadianResponse($data, $response->getStatusCode());
    }

    public function getNonce(int $validity = 30): string
    {
        $response = $this->http->get('nonce', [
            'query' => ['validity' => $validity],
        ]);
        $data = json_decode((string) $response->getBody(), true) ?? [];

        if (empty($data['nonce'])) {
            throw new RuntimeException('Unable to retrieve Moadian nonce.');
        }

        return $data['nonce'];
    }

    private function generateToken(): string
    {
        $nonce = $this->getNonce();
        $payload = [
            'nonce' => $nonce,
            'clientId' => $this->username,
        ];

        return $this->signPayload($payload);
    }

    private function signPayload(array $payload): string
    {
        $cleanCert = $this->cleanPem($this->certificate);

        $headers = [
            'x5c' => [$cleanCert],
            'sigT' => Carbon::now()->toIso8601ZuluString(),
            'crit' => ['sigT'],
            'cty' => 'text/plain',
        ];

        return JWT::encode($payload, $this->privateKey, 'RS256', null, $headers);
    }

    private function encryptPayload(string $jws): string
    {
        $aesKey = random_bytes(32);
        $iv = random_bytes(12);

        $header = [
            'alg' => 'RSA-OAEP-256',
            'enc' => 'A256GCM',
            'kid' => $this->serverKeyId,
        ];

        $encodedHeader = JWT::urlsafeB64Encode((string) json_encode($header, JSON_UNESCAPED_SLASHES));

        // Encrypt AES key with server public key
        $encryptedAesKey = '';
        if (! openssl_public_encrypt($aesKey, $encryptedAesKey, $this->serverPublicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new RuntimeException('Failed to encrypt AES key with server public key.');
        }

        $encodedEncryptedKey = JWT::urlsafeB64Encode($encryptedAesKey);
        $encodedIv = JWT::urlsafeB64Encode($iv);

        // AAD is the encoded header ASCII bytes
        $aad = $encodedHeader;
        $tag = '';
        $ciphertext = openssl_encrypt(
            $jws,
            'aes-256-gcm',
            $aesKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad,
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Failed to encrypt payload with AES-256-GCM.');
        }

        $encodedCiphertext = JWT::urlsafeB64Encode($ciphertext);
        $encodedTag = JWT::urlsafeB64Encode($tag);

        return implode('.', [$encodedHeader, $encodedEncryptedKey, $encodedIv, $encodedCiphertext, $encodedTag]);
    }

    private function ensureServerPublicKey(): void
    {
        if ($this->serverPublicKey !== null) {
            return;
        }

        $serverInfo = $this->getServerInfo();
        if (! $serverInfo->isSuccessful()) {
            throw new RuntimeException('Could not fetch Moadian server public key.');
        }

        $body = $serverInfo->getBody();
        $keyInfo = $body['publicKeys'][0] ?? null;
        if (! $keyInfo) {
            throw new RuntimeException('Moadian server returned no public keys.');
        }

        $this->serverKeyId = $keyInfo['id'];
        $rawKey = $keyInfo['key'];
        $pem = chunk_split($rawKey, 64, "\n");
        $this->serverPublicKey = "-----BEGIN PUBLIC KEY-----\n".$pem."-----END PUBLIC KEY-----\n";
    }

    private function cleanPem(string $cert): string
    {
        return preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\r|\n|\s/', '', $cert);
    }
}
