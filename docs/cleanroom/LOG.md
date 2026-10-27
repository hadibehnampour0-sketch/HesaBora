# CLEAN-ROOM OPERATION LOG
**Audit Ledger Reference:** `docs/cleanroom/LEDGER.csv`  
**Purpose:** Comprehensive evidence trail of clean-room derivations, specifications, role isolation, and verification scans.

---

## Log Entry CR0-01 — 2026-10-27T17:00:00+03:30
- **Phase:** CR-0 (Setup & Roles) & CR-1 (Derivation Analysis & Ledger)
- **Actor/Role:** Lead Systems Architect & Derivation Auditor (Analyst/Verifier)
- **Actions Executed:**
  1. Configured upstream mirror outside the repository working tree at:  
     `C:\Users\Arad\.gemini\antigravity\brain\f24b351f-3520-47c4-b91a-e7eefbd430d4\scratch\upstream-mirror`  
     Extracted exact archive from `remotes/upstream/main` (`483e3606bdfd64aafd5a16bd40b5fc63d9135641`) and fork point (`12e3874a6df0805f4a5d9c643da0585381b7c56d`).
  2. Scanned all 2,080 tracked git files. Computed line-survival and token similarity against upstream mirror.
  3. Generated initial `docs/cleanroom/LEDGER.csv` categorizing every tracked file into:
     - `UPSTREAM-ORIGINAL`: 981 files
     - `UPSTREAM-MODIFIED(>20%)`: 68 files
     - `DAKHL-ORIGINAL`: 679 files
     - `THIRD-PARTY(licensed)`: 22 files
     - `FRAMEWORK / BINARY / ASSET`: 330 files
  4. Identified and cataloged protected core accounting files (`DocumentService`, `TransactionBuilder`, `SubjectService`, `FiscalYearService`, `PaymentService`, `ChequeService`, etc.).
  5. Established clean-room governance rules, role boundaries, and directory layout (`README.md`, `LOG.md`, `SPECS/`, `SIMILARITY/`).
  6. Prepared comprehensive derivation baseline report: `docs/cleanroom/CR1_DERIVATION_REPORT.md`.
- **Files Modified in Production:** ZERO. Read-only setup complete.
- **Result:** Ready for Owner Ratification before CR-2 dependency replacements.

---

## Log Entry CR2-01 — 2026-10-27T18:45:00+03:30
- **Phase:** CR-2 (Clean-Room Dependency Replacements: `jdf.php`, `jooyeshgar/moadian`, `mpdf/mpdf`)
- **Actor/Role:** Lead Systems Architect & Senior Accountant (Implementer/Verifier)
- **Actions Executed:**
  1. **CR-2.1 (`app/Helpers/jdf.php` Elimination)**:
     - Implemented zero-dependency clean-room Iranian calendar engine `App\Support\JalaliDate` (MIT) based on the Birashk / Borkowski 33-year cycle algorithm (years 1200–1500+).
     - Defined global compatibility wrappers in `app/Helpers/helpers.php` (`gregorian_to_jalali`, `jalali_to_gregorian`, `jcheckdate`, `jdate`).
     - Removed `app/Helpers/jdf.php` from `composer.json` and deleted `app/Helpers/jdf.php`.
     - Unit-verified via `tests/Unit/JalaliDateEquivalenceTest.php` (4/4 passed, 251 assertions).
     - Resolved single edge-case in `MonthlyBudgetTest` by ensuring `%04d%s%02d%s%02d` zero-padded string output for SQL date range queries. Verified all 43 tests in `MonthlyBudgetTest` pass.
  2. **CR-2.2 (`carlos-meneses/laravel-mpdf` & `mpdf/mpdf` GPL-2.0-only Replacement)**:
     - Designed clean-room PDF abstraction layer: `App\Support\Pdf\Pdf` and `App\Support\Pdf\PdfDocument` (MIT).
     - Decoupled `ReportExportService` from mPDF; migrated to `App\Support\Pdf\Pdf` facade.
     - Replaced `carlos-meneses/laravel-mpdf` with `barryvdh/laravel-dompdf` (MIT / LGPL-2.1).
     - Verified via `SeasonalTaxReportTest` (10/10 passed), `ReportEmailTest` (17/17 passed), and `PdfCleanRoomTest` (2/2 passed).
  3. **CR-2.3 (`jooyeshgar/moadian` GPL-3.0 Replacement)**:
     - Implemented clean-room native Moadian client suite under `App\Support\Moadian\` (MIT):
       - `Verhoeff.php`: Dihedral group D5 checksum & validation algorithm.
       - `TaxIdGenerator.php`: Iranian Tax Administration 22-character unique tax ID generator.
       - `InvoiceHeader.php`, `InvoiceItem.php`, `Payment.php`, `MoadianInvoice.php`: Official INTA e-invoice DTOs.
       - `MoadianClient.php`: Guzzle-based client with JWS (RS256) signing via `firebase/php-jwt` and JWE (AES-256-GCM + RSA-OAEP) encryption.
       - `MoadianManager.php` & `Facades\Moadian.php`: Laravel facade matching required signatures for mockability.
     - Updated `app/Services/MoadianService.php` to import `App\Support\Moadian`.
     - Removed `jooyeshgar/moadian` from `composer.json` and updated composer lock file.
     - Verified via `MoadianServiceTest` (10/10 passed), `MoadianReconciliationTest` (5/5 passed), `MoadianWorkQueueTest` (4/4 passed), and `MoadianCleanRoomTest` (4/4 passed).
  4. **Dependency License Audit Post CR-2**:
     - Ran `composer licenses`. Verified that zero single-license GPL or AGPL dependencies remain in the project.
- **Full Test Suite Status:** 99 passed (662 assertions) across all affected modules with zero regressions.

---

## Log Entry CR-B-01 — 2026-10-27T19:50:00+03:30
- **Phase:** Phase B (Ed25519 Modular Licensing Re-Architecture & "Never Re-Gate" Enforcement — D-125 & D-026)
- **Actor/Role:** Lead Systems Architect & Senior Accountant (Implementer/Verifier)
- **Actions Executed:**
  1. **Ed25519 Cryptographic Layer**:
     - Introduced `paragonie/sodium_compat` (`^2.5`) providing verified constant-time Ed25519 asymmetric verification in pure PHP when native C `ext-sodium` is absent.
     - Implemented `App\Support\License\LicenseCrypto` (canonical JSON sorting, Base64URL encoding/decoding, detached signature verification, keypair generation, and self-testing).
     - Defined `config/license.php` containing root verification public key `dakhl-root-2026` (`b79f7b64c4e0f5a6f5e1ad74c2a6d1ee34a03d595472825cada05b46b4002e11`), default grace period (14 days), and revocation blocklist.
  2. **Offline Key Issuer Tooling**:
     - Built `tools/license-issuer/dakhl-license.php` supporting `keygen`, `issue`, and `verify`.
     - Gitignored `tools/license-issuer/keys/` and `*.key` so private keys are never committed or distributed in application builds.
  3. **License Service & Controller Hardening**:
     - Rewrote `app/Services/LicenseService.php` with `DAKHL-LIC-v2` token support, deprecating v1 HMAC tokens with clear migration guidance.
     - Enforced "Never Re-Gate" contract (D-026 / Master Context §39.6): `tax_moadian` is guaranteed in the `basic` tier alongside core accounting, invoicing, inventory, and cheques.
     - Implemented 14-day grace period with non-destructive read-only mode upon hard expiration (D-024) in `EnsureModuleLicensed` middleware.
     - Disabled in-app key generation in production builds (`abort_if(app()->isProduction(), 403)`).
  4. **Jalali Date Convenience Helpers**:
     - Added clean-room compatibility functions `jalali_to_gregorian_date`, `gregorian_to_jalali_date`, `gregorian_to_jalali_datetime`, and `justDate` to `app/Helpers/helpers.php` backed by `App\Support\JalaliDate`.
  5. **Verification**:
     - `ModularLicensingTest`: 21/21 passed (2,859 assertions).
     - Full clean-room test suite: 31/31 passed (3,136 assertions).
     - Core accounting and warehouse suites: 100% passed without regressions.


