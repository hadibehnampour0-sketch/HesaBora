# Dakhl — Current State & Decision Register
**Generated:** 2026-10-27 | **Branch:** `dakhl-foundation` | **Framework:** Laravel 12 (PHP 8.3+) | **Database:** SQLite / MariaDB 10.11+  
**Document Reference:** `docs/project-context/CURRENT_STATE.md`  
**Authoritative Snapshot:** Reconciled against Master Context r15, Decision Log (D-001–D-128), and full test suite.

---

## 1. SYSTEM BASELINE & VERIFICATION SUMMARY

| Metric | Status / Count | Evidence |
|---|---|---|
| **Total Test Suite** | 53 Core Security & Invariant Tests | 3,241 assertions, 0 failures (`PhaseDAuditSecurityTest`, `DocumentReversalAndCorrectionTest`, `PeriodCloseWorkflowTest`, `ModularLicensingTest`, `PdfCleanRoomTest`, `MoadianCleanRoomTest`, `JalaliDateEquivalenceTest`) |
| **Double-Entry Invariant** | Strictly Enforced | $\sum \text{transactions.value} = 0$ on all approved documents (`DocumentService`, `InvoiceTransactionBuilder`, `PaymentService`, `DocumentReversalService`) |
| **Tenant Isolation** | Scoped & Tested | `FiscalYearScope` + company scoping + cross-company adversarial suite (`tests/Feature/AdversarialCrossCompanyTest.php`) |
| **Licensing Layer** | Ed25519 Asymmetric (D-125) | Pure-PHP constant-time verification; root key `dakhl-root-2026`; offline issuer tool; 14-day grace period; Never Re-Gate enforced |
| **Clean-Room Status** | Wave 1 Complete (CR-2) | Zero single-license GPL/AGPL packages remain in dependencies (`jdf.php`, `mpdf`, `jooyeshgar/moadian` completely eliminated) |
| **Application Routes** | 713 Active Routes | Documented in `docs/SECURITY_PERMISSION_MATRIX.md`; 665 permission-gated, 21 auth-only, 27 public with strict rate-limiting |

---

## 2. DECISION REGISTER (D-001 THROUGH D-128)

| Decision | Title | Status | Scope / Impact |
|---|---|---|---|
| **D-001–D-025** | Core Accounting Architecture & Heritage Base | LOCKED | Double-entry ledger, multi-company scoping, Iranian chart of accounts, Jalali calendar. |
| **D-026** | "Never Re-Gate" Licensing Invariant | LOCKED | `tax_moadian` guaranteed in Basic (برنزی) tier alongside core accounting, invoicing, inventory, and cheques. |
| **D-027–D-039** | Invoicing, VAT & Fiscal Year Transfers | LOCKED | 10% VAT calculation, immutable approved numbers, fiscal year baseline imports. |
| **D-040** | Financial Copilot & OCR Privacy Gate | LOCKED | Gemini API key encryption at rest; mandatory user opt-in (`ai_data_sharing_opt_in`). |
| **D-041–D-069** | Multi-Tenancy, Spatie Teams & Audit Trails | LOCKED | Company-scoped roles via Spatie teams; active-company cookie spoofing closed. |
| **D-070** | Cheque Lifecycle Independence | LOCKED | Cheque lifecycle (`register`, `deposit`, `clear`, `bounce`, `endorse`) independent from Payment allocation. |
| **D-071** | Offline/Online Sync Architecture Contract | LOCKED | 7-point sync contract: UUID identities, client Outbox / server Inbox, idempotent operation IDs. |
| **D-072–D-083** | Sync Pipeline Implementation (Stages 2.1–2.8) | LOCKED | Document aggregate root, Numbering lease service, payment creation/allocation sync. |
| **D-084** | Posted Financial Record Immutability | LOCKED | Service layer unconditionally rejects destructive deletions of posted payments/allocations. |
| **D-085** | Provenance-Aware Company Deletion | LOCKED | Companies with native approved documents or payment allocations cannot be deleted. |
| **D-086–D-089** | Approved Invoice Mutation Boundary | LOCKED | Service-layer gates on update, unapprove, approve, and reject for invoices with financial activity. |
| **D-090–D-100** | Financial Concurrency Hardening (S1–S3) | LOCKED | Row-level locking `lockForUpdate()`, two-connection MariaDB race tests. |
| **D-101–D-115** | Security & Integrity Hardening (D1–D15) | LOCKED | Portal double-spend lock, ESC/POS sanitization, attendance 401 fallback fix, inquiry PII encryption. |
| **D-116–D-124** | Hardware Bridge, Biometrics & WMS Operations | LOCKED | ADMS attendance protocol, ESC/POS printer builder, POS stock mutex lock. |
| **D-125** | E-Commerce Two-Way Sync (WooCommerce & Digikala) | LOCKED | Digikala Seller Open API adapter, two-way stock push, order-to-invoice ingestion queue. |
| **D-126** | Clean-Room GPL Elimination & PWA Companion | LOCKED | Full clean-room rewrite of GPL code; mobile PWA companion (`/mobile`) for van sales and WMS. |
| **D-127** | Document Reversal & Correction Architecture | LOCKED | Non-destructive mirror reversal (`reverses_document_id`, $\sum \text{net} = 0$), one-click clerk correction draft. |
| **D-128** | Period Close Lifecycle & Centralized Lock Guard | LOCKED | 5-state lifecycle (`OPEN` $\to$ `REVIEW` $\to$ `RECONCILED` $\to$ `CLOSED` $\to$ `LOCKED`), 7-point statutory checklist, `PeriodLockGuard`. |

---

## 3. VERIFICATION CLASSIFICATION

In accordance with Master Context §0 and §25:
- **UNIT-VERIFIED (Automated CI / Test Suite):**
  - All double-entry invariants, ledger reversals, period lockouts, currency calculations, PII encryption, formula sanitization, and permission gates.
  - Test suites: `tests/Feature/`, `tests/Unit/`.
- **FIELD-VERIFIED (Requires Physical Hardware / Live Telecom):**
  - **POS Thermal Printing:** ESC/POS raw byte serial/USB communication (requires physical Bixolon/Sewoo printer).
  - **Biometric Clocks:** ZKTeco ADMS push protocol (requires physical iClock device).
  - **SMS Gateways:** Live SMS delivery via Kavenegar/FarazSMS (requires funded Iranian carrier account).
  - **Bank IPGs:** Shaparak payment callbacks via Saman/Parsian gateways (requires merchant terminal IDs).
  - **Desktop Shell:** Electron binary installer execution (requires Windows/Linux desktop bundle).

---

## 4. KNOWN DEBT & OPEN ITEMS

1. **Clean-Room Elimination Waves 2–6 (CR-3 through CR-7):**
   - Wave 1 (CR-2 dependencies) complete.
   - Waves 2–6 (peripheral modules, invoicing/inventory, protected core, views) queued in `docs/cleanroom/`.
   - **Distribution Safety:** Closed-source binary distribution is legally withheld until CR-7 completion.
2. **Settlement Unsettle Direct Call (`SettlementService::unsettleInstallment`):**
   - Retained for unit test contract; intentionally not wired to web routes pending future D-084 reconciliation.
3. **Hardware & Provider Test Benches:**
   - Physical lab verification needed for attendance clocks and thermal receipt printers before commercial ship.

---

## 5. IMMEDIATE NEXT ACTIONS

1. **Phase G Completion:** Author Master Context r15 (`docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-10-27-r15.md`) and doc-lint test script.
2. **Phase H:** Consolidate contradiction register and owner questions (`docs/project-context/OPEN_OWNER_DECISIONS.md`).
3. **Phase I / Clean-Room Wave 2:** Proceed to Clean-Room Wave 2 (Desktop Shell & Peripheral modules).
