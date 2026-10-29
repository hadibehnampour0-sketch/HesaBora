<?php

namespace App\Support\License;

use ParagonIE_Sodium_Compat;
use RuntimeException;

/**
 * Clean-Room Asymmetric Ed25519 Cryptographic Engine for Dakhl Licensing (D-125).
 *
 * Uses libsodium (via ParagonIE_Sodium_Compat polyfill when native extension is unavailable)
 * to provide robust, mathematically unforgeable detached Ed25519 signature verification.
 *
 * License: MIT
 */
class LicenseCrypto
{
    /**
     * Verify an Ed25519 signature against canonical payload bytes.
     *
     * @param  string  $payloadJson  Exact JSON bytes that were signed
     * @param  string  $signatureBinary  64-byte binary signature
     * @param  string  $publicKeyBinary  32-byte binary public key
     */
    public static function verify(string $payloadJson, string $signatureBinary, string $publicKeyBinary): bool
    {
        if (strlen($signatureBinary) !== 64 || strlen($publicKeyBinary) !== 32) {
            return false;
        }

        try {
            if (function_exists('sodium_crypto_sign_verify_detached')) {
                return sodium_crypto_sign_verify_detached($signatureBinary, $payloadJson, $publicKeyBinary);
            }

            return ParagonIE_Sodium_Compat::crypto_sign_verify_detached($signatureBinary, $payloadJson, $publicKeyBinary);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Sign canonical payload bytes using a 64-byte Ed25519 secret key.
     * Used by the offline issuer tool and in isolated test suites.
     */
    public static function sign(string $payloadJson, string $secretKeyBinary): string
    {
        if (strlen($secretKeyBinary) !== 64) {
            throw new RuntimeException('Invalid secret key length; Ed25519 secret key must be 64 bytes.');
        }

        if (function_exists('sodium_crypto_sign_detached')) {
            return sodium_crypto_sign_detached($payloadJson, $secretKeyBinary);
        }

        return ParagonIE_Sodium_Compat::crypto_sign_detached($payloadJson, $secretKeyBinary);
    }

    /**
     * Produce canonical JSON with alphabetically sorted keys and fixed formatting.
     */
    public static function canonicalJson(array $data): string
    {
        self::ksortRecursive($data);

        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): ?string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    /**
     * Generate an Ed25519 keypair for key generation tools and tests.
     *
     * @return array{keypair: string, public_key: string, secret_key: string}
     */
    public static function generateKeypair(): array
    {
        if (function_exists('sodium_crypto_sign_keypair')
            && function_exists('sodium_crypto_sign_publickey')
            && function_exists('sodium_crypto_sign_secretkey')) {
            $keypair = sodium_crypto_sign_keypair();

            return [
                'keypair' => $keypair,
                'public_key' => sodium_crypto_sign_publickey($keypair),
                'secret_key' => sodium_crypto_sign_secretkey($keypair),
            ];
        }

        $keypair = ParagonIE_Sodium_Compat::crypto_sign_keypair();

        return [
            'keypair' => $keypair,
            'public_key' => ParagonIE_Sodium_Compat::crypto_sign_publickey($keypair),
            'secret_key' => ParagonIE_Sodium_Compat::crypto_sign_secretkey($keypair),
        ];
    }

    /**
     * Cryptographic self-test verifying Ed25519 capability.
     */
    public static function selfTest(): bool
    {
        try {
            if (function_exists('sodium_crypto_sign_keypair')
                && function_exists('sodium_crypto_sign_publickey')
                && function_exists('sodium_crypto_sign_secretkey')
                && function_exists('sodium_crypto_sign_detached')
                && function_exists('sodium_crypto_sign_verify_detached')) {
                $keypair = sodium_crypto_sign_keypair();
                $publicKey = sodium_crypto_sign_publickey($keypair);
                $secretKey = sodium_crypto_sign_secretkey($keypair);

                $message = 'dakhl-selftest-native-'.hrtime(true);
                $sig = sodium_crypto_sign_detached($message, $secretKey);

                if (sodium_crypto_sign_verify_detached($sig, $message, $publicKey)) {
                    return true;
                }
            }

            $keypair = ParagonIE_Sodium_Compat::crypto_sign_keypair();
            $publicKey = ParagonIE_Sodium_Compat::crypto_sign_publickey($keypair);
            $secretKey = ParagonIE_Sodium_Compat::crypto_sign_secretkey($keypair);

            $message = 'dakhl-selftest-compat-'.hrtime(true);
            $sig = ParagonIE_Sodium_Compat::crypto_sign_detached($message, $secretKey);

            return ParagonIE_Sodium_Compat::crypto_sign_verify_detached($sig, $message, $publicKey);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function ksortRecursive(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }
    }
}
