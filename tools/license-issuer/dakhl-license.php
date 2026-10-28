<?php

/**
 * Dakhl Offline License Issuer Tool (CLI).
 *
 * Standalone tool for issuing and signing DAKHL-LIC-v2 asymmetric licenses.
 * MUST NOT be included in production application builds.
 *
 * License: MIT
 */
if (php_sapi_name() !== 'cli') {
    exit("CLI only.\n");
}

$autoloadPath = dirname(__DIR__, 2).'/vendor/autoload.php';
if (! file_exists($autoloadPath)) {
    exit("Error: vendor/autoload.php not found. Run composer install.\n");
}
require $autoloadPath;

use App\Support\License\LicenseCrypto;
use Ramsey\Uuid\Uuid;

$options = getopt('', [
    'action:',
    'keygen',
    'issue',
    'rebind',
    'verify',
    'key:',
    'pubkey:',
    'kid:',
    'lic_id:',
    'rebind_to:',
    'tier:',
    'org:',
    'nid:',
    'days:',
    'fingerprint:',
    'modules:',
    'token:',
    'out:',
]);

// Fallback argument parser for robust CLI execution across platforms
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--')) {
        $parts = explode('=', substr($arg, 2), 2);
        $options[$parts[0]] = $parts[1] ?? true;
    }
}

$action = null;
if (isset($options['keygen'])) {
    $action = 'keygen';
} elseif (isset($options['issue'])) {
    $action = 'issue';
} elseif (isset($options['rebind'])) {
    $action = 'rebind';
} elseif (isset($options['verify'])) {
    $action = 'verify';
} else {
    $action = $options['action'] ?? ($argv[1] ?? null);
}

switch ($action) {
    case 'keygen':
        runKeygen($options);
        break;
    case 'issue':
        runIssue($options);
        break;
    case 'rebind':
        runRebind($options);
        break;
    case 'verify':
        runVerify($options);
        break;
    default:
        echo "Usage:\n";
        echo "  php dakhl-license.php keygen [--out=keys/] [--kid=name]\n";
        echo "  php dakhl-license.php issue --key=path.key --kid=id --tier=basic|professional|enterprise --org=\"Name\" [--days=365] [--fingerprint=*]\n";
        echo "  php dakhl-license.php rebind --key=path.key --kid=id --lic_id=UUID --rebind_to=DAKHL-...\n";
        echo "  php dakhl-license.php verify --token=DAKHL-LIC-v2... [--pubkey=path.pub]\n";
        exit(1);
}

function runKeygen(array $options): void
{
    $outDir = $options['out'] ?? __DIR__.'/keys';
    if (! is_dir($outDir)) {
        mkdir($outDir, 0700, true);
    }

    $kid = $options['kid'] ?? 'dakhl-root-'.date('Y');
    $kp = ParagonIE_Sodium_Compat::crypto_sign_keypair();
    $pk = ParagonIE_Sodium_Compat::crypto_sign_publickey($kp);
    $sk = ParagonIE_Sodium_Compat::crypto_sign_secretkey($kp);

    $pubPath = $outDir.'/'.$kid.'.pub';
    $keyPath = $outDir.'/'.$kid.'.key';

    file_put_contents($pubPath, bin2hex($pk));
    file_put_contents($keyPath, bin2hex($sk));

    echo "Generated Ed25519 keypair for '{$kid}':\n";
    echo "  Public Key:  {$pubPath} (".bin2hex($pk).")\n";
    echo "  Private Key: {$keyPath} [PROTECTED]\n";
}

function runIssue(array $options): void
{
    $keyPath = $options['key'] ?? null;
    if ($keyPath && ! file_exists($keyPath) && file_exists(getcwd().'/'.$keyPath)) {
        $keyPath = getcwd().'/'.$keyPath;
    }
    if ($keyPath && ! file_exists($keyPath) && file_exists(__DIR__.'/'.$keyPath)) {
        $keyPath = __DIR__.'/'.$keyPath;
    }

    if (! $keyPath || ! file_exists($keyPath)) {
        exit("Error: --key=<path to private key file> is required.\n");
    }

    $secretHex = trim((string) file_get_contents($keyPath));
    $secretKey = hex2bin($secretHex);
    if (strlen($secretKey) !== 64) {
        exit("Error: Invalid private key. Must be 64 bytes (128 hex chars).\n");
    }

    $kid = $options['kid'] ?? 'dakhl-root-2026';
    $tier = $options['tier'] ?? 'professional';
    $org = $options['org'] ?? 'مشتری گرامی دخل';
    $days = isset($options['days']) ? (int) $options['days'] : 365;
    $fingerprint = $options['fingerprint'] ?? '*';

    $now = time();
    $expiresAt = $days > 0 ? $now + ($days * 86400) : null;

    $modules = match ($tier) {
        'basic' => [
            'core_accounting',
            'sales_purchase',
            'inventory_warehouse',
            'cheques_treasury',
            'tax_moadian',
        ],
        'professional' => [
            'core_accounting',
            'sales_purchase',
            'inventory_warehouse',
            'cheques_treasury',
            'tax_moadian',
            'pos_retail',
            'customer_loyalty',
            'sms_automation',
            'official_inquiries',
            'installment_financing',
            'migration_tools',
        ],
        'enterprise' => ['*'],
        default => isset($options['modules']) ? explode(',', $options['modules']) : ['*'],
    };

    $limits = match ($tier) {
        'basic' => ['companies' => 1, 'users' => 2, 'devices' => 2],
        'professional' => ['companies' => 3, 'users' => 5, 'devices' => 5],
        'enterprise' => ['companies' => -1, 'users' => -1, 'devices' => -1],
        default => ['companies' => 1, 'users' => 2, 'devices' => 1],
    };

    $payload = [
        'v' => 2,
        'kid' => $kid,
        'lic_id' => Uuid::uuid4()->toString(),
        'org' => $org,
        'customer_national_id' => $options['nid'] ?? null,
        'tier' => $tier,
        'modules' => $modules,
        'limits' => $limits,
        'issued_at' => $now,
        'not_before' => $now,
        'expires_at' => $expiresAt,
        'grace_days' => 14,
        'fingerprint' => $fingerprint,
        'issuer' => 'Dakhl Licensing Authority',
        'nonce' => bin2hex(random_bytes(8)),
    ];

    $canonicalJson = LicenseCrypto::canonicalJson($payload);
    $signatureBinary = LicenseCrypto::sign($canonicalJson, $secretKey);

    $token = sprintf(
        'DAKHL-LIC-v2.%s.%s',
        LicenseCrypto::base64UrlEncode($canonicalJson),
        LicenseCrypto::base64UrlEncode($signatureBinary)
    );

    echo "--- LICENSE ISSUED ---\n";
    echo $token."\n";
    echo "----------------------\n";
    echo "Organization: {$org}\n";
    echo "Tier:         {$tier}\n";
    echo 'Expires:      '.($expiresAt ? date('Y-m-d', $expiresAt) : 'Never (Lifetime)')."\n";
    echo "Fingerprint:  {$fingerprint}\n";
}

function runVerify(array $options): void
{
    $token = $options['token'] ?? null;
    if (! $token) {
        exit("Error: --token=<DAKHL-LIC-v2...> is required.\n");
    }

    $parts = explode('.', trim($token));
    if (count($parts) !== 3 || $parts[0] !== 'DAKHL-LIC-v2') {
        exit("Error: Invalid token format. Must be DAKHL-LIC-v2.{payload}.{signature}\n");
    }

    $payloadJson = LicenseCrypto::base64UrlDecode($parts[1]);
    $signature = LicenseCrypto::base64UrlDecode($parts[2]);

    if (! $payloadJson || ! $signature) {
        exit("Error: Failed to base64url decode token parts.\n");
    }

    $payload = json_decode($payloadJson, true);
    if (! is_array($payload)) {
        exit("Error: Payload is not valid JSON.\n");
    }

    $pubKeyHex = null;
    if (! empty($options['pubkey'])) {
        $pubKeyHex = file_exists($options['pubkey'])
            ? trim((string) file_get_contents($options['pubkey']))
            : trim((string) $options['pubkey']);
    } else {
        $configKeys = config('license.keys', []);
        $pubKeyHex = $configKeys[$payload['kid'] ?? ''] ?? null;
    }

    if (! $pubKeyHex) {
        exit("Error: No public key found for kid '{$payload['kid']}'. Provide --pubkey.\n");
    }

    $valid = LicenseCrypto::verify($payloadJson, $signature, hex2bin($pubKeyHex));
    if ($valid) {
        echo "[VERIFIED] Digital signature is VALID (Ed25519).\n";
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
    } else {
        echo "[FAILED] Digital signature is INVALID or corrupted.\n";
        exit(1);
    }
}

function runRebind(array $options): void
{
    if (empty($options['key']) || ! file_exists($options['key'])) {
        exit("Error: Private key file not found. Provide --key=path.key\n");
    }

    if (empty($options['lic_id'])) {
        exit("Error: Target license ID is required. Provide --lic_id=UUID\n");
    }

    if (empty($options['rebind_to'])) {
        exit("Error: Target hardware fingerprint is required. Provide --rebind_to=DAKHL-...\n");
    }

    $secretKeyHex = trim((string) file_get_contents($options['key']));
    $kid = $options['kid'] ?? basename($options['key'], '.key');

    $payload = [
        'v' => 2,
        'type' => 'rebind',
        'kid' => $kid,
        'lic_id' => $options['lic_id'],
        'rebind_to' => $options['rebind_to'],
        'reason' => $options['reason'] ?? 'مجوز انتقال به سخت‌افزار جدید',
        'issued_at' => time(),
        'nonce' => bin2hex(random_bytes(8)),
    ];

    $canonicalJson = LicenseCrypto::canonicalJson($payload);
    $signature = LicenseCrypto::sign($canonicalJson, hex2bin($secretKeyHex));

    $token = sprintf(
        'DAKHL-LIC-REBIND-v2.%s.%s',
        LicenseCrypto::base64UrlEncode($canonicalJson),
        LicenseCrypto::base64UrlEncode($signature)
    );

    echo "\n=== ISSUED DAKHL REBIND TOKEN ===\n";
    echo $token."\n\n";
}
