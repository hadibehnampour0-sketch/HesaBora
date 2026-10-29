# Dakhl Licensing: Ed25519 Key Generation & Rotation Guide

**Status:** Official Protocol  
**Scope:** Offline Production Key Management, Key ID (`kid`) Rotation, and Key Revocation  
**Normative Decisions:** D-026, D-125, D-129  

---

## 1. Cryptographic Architecture & Environment Isolation

Dakhl utilizes asymmetric Ed25519 elliptic-curve digital signatures for licensing:
- **Application Runtime (Public Verifier):** Contains **only** the 32-byte public verification key in `config/license.php`. The application can verify tokens but can **never** issue or forge them.
- **Offline Issuer (Private Signer):** The 64-byte Ed25519 secret key is stored **exclusively offline on the owner's secure workstation**. It is excluded from the repository (`.gitignore`), from all git history, and from application distribution builds (`config/nativephp.php`).

> [!WARNING]
> **DEV-ONLY KEYPAIR NOTICE:**  
> The embedded key `dakhl-root-2026` (`b79f7b64c4e0f5a6f5e1ad74c2a6d1ee34a03d595472825cada05b46b4002e11`) is strictly designated for **DEVELOPMENT AND AUTOMATED TESTING (DEV-ONLY)**.  
> It must **never** be used to sign commercial production customer licenses.

---

## 2. Generating a Production Keypair (Owner Offline Procedure)

To create a production keypair on a secure, offline machine:

```bash
php tools/license-issuer/dakhl-license.php --keygen --kid=dakhl-prod-2026 --out=/path/to/secure/storage/
```

This generates two files in the target directory:
1. `dakhl-prod-2026.key`: The 64-byte secret key (hex encoded). **Store securely in password manager / cold storage. NEVER commit to Git.**
2. `dakhl-prod-2026.pub`: The 32-byte public verification key (hex encoded).

---

## 3. Registering the Production Public Key in the Application

Add the public key to `config/license.php` or inject it via `.env`:

```env
# In production .env
DAKHL_LICENSE_PUBKEY_PROD="<64-hex-characters-from-dakhl-prod-2026.pub>"
DAKHL_LICENSE_DEFAULT_KID="dakhl-prod-2026"
```

Or directly in `config/license.php`:

```php
'keys' => [
    'dakhl-root-2026' => 'b79f7b64c4e0f5a6f5e1ad74c2a6d1ee34a03d595472825cada05b46b4002e11', // DEV-ONLY
    'dakhl-prod-2026' => env('DAKHL_LICENSE_PUBKEY_PROD', '<public-key-hex>'),
],
```

---

## 4. Issuing a Production License Token

Execute the issuer CLI tool on your offline machine using the production secret key:

```bash
php tools/license-issuer/dakhl-license.php --issue \
    --key=/path/to/secure/storage/dakhl-prod-2026.key \
    --kid=dakhl-prod-2026 \
    --org="شرکت بازرگانی نمونه" \
    --nid="10101234567" \
    --tier=business \
    --days=365 \
    --fingerprint="<customer-machine-fingerprint-or-*>"
```

Output format: `DAKHL-LIC-v2.<canonical-json-base64url>.<signature-base64url>.<kid>`

---

## 5. Key Rotation & Compromise Revocation Protocol

If a production key is scheduled for routine annual rotation, or if a private key is suspected of compromise:

1. **Generate a new keypair:**
   ```bash
   php tools/license-issuer/dakhl-license.php --keygen --kid=dakhl-prod-2027 --out=/path/to/secure/storage/
   ```
2. **Add the new public key to `config/license.php`:**
   ```php
   'keys' => [
       'dakhl-prod-2026' => env('DAKHL_LICENSE_PUBKEY_2026', '...'),
       'dakhl-prod-2027' => env('DAKHL_LICENSE_PUBKEY_2027', '...'),
   ],
   ```
3. **If compromised, revoke the old `kid`:**
   Add the compromised `kid` to `revoked_kids` in `config/license.php`:
   ```php
   'revoked_kids' => [
       'dakhl-prod-2026', // Revoked due to scheduled rotation / compromise
   ],
   ```
4. **Result:**  
   - Any token signed with `dakhl-prod-2026` will be rejected immediately upon application boot or verification.
   - Customers with valid licenses under `dakhl-prod-2026` can be re-issued a new token under `dakhl-prod-2027` without downtime using the rebind command:
   ```bash
   php tools/license-issuer/dakhl-license.php --rebind \
       --key=/path/to/secure/storage/dakhl-prod-2027.key \
       --kid=dakhl-prod-2027 \
       --token="<old-token>"
   ```
