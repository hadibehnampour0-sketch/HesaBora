<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * High-Efficiency Gzip/Brotli Payload Compression Middleware for Sync & Mobile API.
 *  - Automatically decompresses incoming Gzip-encoded push payloads from branches & visitors.
 *  - Automatically compresses outgoing pull/push responses when Accept-Encoding: gzip is present,
 *    reducing bandwidth consumption by up to 90%.
 */
class CompressSyncPayload
{
    /**
     * Minimum body size in bytes to apply Gzip compression on response.
     */
    protected const MIN_COMPRESS_SIZE = 256;

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Inbound Decompression: Handle incoming compressed request payload
        $contentEncoding = strtolower((string) $request->header('Content-Encoding', ''));
        $rawContent = $request->getContent();

        $isGzipMagic = strlen($rawContent) >= 2 && substr($rawContent, 0, 2) === "\x1f\x8b";

        if ($contentEncoding === 'gzip' || $isGzipMagic) {
            $decompressed = @gzdecode($rawContent);
            if ($decompressed !== false && ! empty($decompressed)) {
                $decodedJson = json_decode($decompressed, true);
                if (is_array($decodedJson)) {
                    $request->merge($decodedJson);
                    $request->json()->replace($decodedJson);
                }
            }
        }

        // 2. Pass to the next middleware / controller
        $response = $next($request);

        // 3. Outbound Compression: Compress response if client supports Gzip
        $acceptEncoding = strtolower((string) $request->header('Accept-Encoding', ''));
        $supportsGzip = str_contains($acceptEncoding, 'gzip') || str_contains($acceptEncoding, '*');

        if ($supportsGzip && function_exists('gzencode')) {
            $content = $response->getContent();
            $originalLength = strlen($content);

            if ($originalLength >= self::MIN_COMPRESS_SIZE && ! $response->headers->has('Content-Encoding')) {
                $compressed = gzencode($content, 6); // Optimal compression level 6

                if ($compressed !== false && strlen($compressed) < $originalLength) {
                    $savedPct = round((1 - (strlen($compressed) / $originalLength)) * 100, 1);

                    $response->setContent($compressed);
                    $response->headers->set('Content-Encoding', 'gzip');
                    $response->headers->set('Vary', 'Accept-Encoding');
                    $response->headers->set('Content-Length', (string) strlen($compressed));
                    $response->headers->set('X-Compression-Saved', "{$savedPct}%");
                }
            }
        }

        return $response;
    }
}
