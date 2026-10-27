# Dakhl (دخل) — Open Owner Decisions & Contradiction Register

**Document Version:** 1.0.0  
**Date:** 2026-10-27  
**Branch:** `dakhl-foundation`  
**Authors:** Chief Systems Architect, Senior Accountant, Senior Backend Engineer, Senior Android Engineer, RTL UI/UX Designer, Accounting Clerk, Finance Manager  
**Reference Documents:** `Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-10-27-r15.md`, `CURRENT_STATE.md`, `Dakhl_DECISION_LOG_2026-09-12-r4.md`

---

## 1. PURPOSE & EXECUTIVE SUMMARY

During the execution of Super Prompts r15 and r16 (Phases A through G), several architectural trade-offs, legal boundaries, physical verification gates, and domain policies were analyzed across all seven operational roles.

While code-level integrity has been hardened to 100% green tests (3,386 assertions), five critical areas contain contradictions between legacy assumptions, roadmap aspirations, and legal or accounting reality. This register documents these contradictions and formally requests owner decisions.

---

## 2. CONTRADICTION REGISTER

### Item 1: Trade / Consignment Architecture Activation vs Core Accounting Invariants
- **Background:** Decision **D-074** explicitly locked the Trade/Consignment architecture (`Trade`, `Deal`, `Consignment`, `Open Exchange`, `Position`, multi-unit `Valuation`) as **documentation-only, future architecture**.
- **Contradiction:** Several backlog items and roadmap sections reference gold/jewelry weights (Mithqal/Gram), currency exchange spreads, and consignment stock tracking. Attempting to introduce polymorphic transaction models or parallel valuation engines inside `DocumentService` or `InvoiceTransactionBuilder` would violate double-entry posting invariants ($\sum \text{transactions.value} = 0$) and company scoping.
- **Architect & Accountant Recommendation:** Keep D-074 strictly locked. Any future Trade module must sit as an upstream domain layer generating standard approved `Document` and `Invoice` entities through `InvoiceTransactionBuilder`, rather than bypassing the double-entry core.
- **Owner Question 1:** *Do you confirm that Phase Trade remains purely prospective and that no database migrations or service entities for Trade/Consignment shall be introduced until after Clean-Room Wave 6 is completed?*

---

### Item 2: Direct Installment Unsettle (`unsettleInstallment`) vs Posted Immutability (D-084 & D-127)
- **Background:** Decision **D-084** unconditionally bans destructive mutation or deletion of posted financial records (`Payment`, `PaymentAllocation`). Decision **D-127** introduced non-destructive mirror reversals (`reverses_document_id`).
- **Contradiction:** `App\Services\SettlementService::unsettleInstallment()` directly executes `$allocation->delete()`, bypassing D-084. It is called by `tests/Feature/CheckSettlementTest.php::test_unsettling_an_installment_restores_the_remaining_amount`. However, it is not wired to any HTTP controller or route, so web users cannot trigger it.
- **Auditor & Clerk Recommendation:** The method should NOT be wired to any user-facing endpoint in its current destructive form. When installment un-settlement is introduced to the web UI, it must be rewritten to create an offsetting/reversing allocation record rather than deleting the existing row.
- **Owner Question 2:** *Do you approve keeping `SettlementService::unsettleInstallment()` unwired from web routes, and designating its future user-facing implementation to follow D-127 non-destructive reversal principles?*

---

### Item 3: Clean-Room GPL Elimination & Closed-Source Distribution Policy (D-126)
- **Background:** Phase A audit revealed that upstream base (FreeAmir) is licensed under GPL-3.0. Owner Decision **D-126** mandates a full clean-room rewrite (Phases CR-0 through CR-7) to enable commercial proprietary distribution. Wave 1 (CR-2: dependencies `jdf.php`, `mpdf`, `moadian`) is complete.
- **Legal Reality:** While runtime dependencies are now 100% MIT/LGPL-compliant, historical source code files derived from the upstream repository remain in the codebase until Waves 2 through 6 are completed.
- **Legal & Governance Rule:** Under GPL-3.0, any compiled binary or distributed copy of the software in its current state must provide full corresponding source code under GPL-3.0. Distributing closed-source installers (e.g. NativePHP desktop binaries or private SaaS enterprise builds) before Clean-Room Wave 7 (legal counsel sign-off) would constitute copyright infringement.
- **Owner Question 3:** *Do you confirm that no closed-source binary, packaged desktop installer, or proprietary customer delivery will occur until Clean-Room Phase CR-7 is formally signed off?*

---

### Item 4: Verification Classification — UNIT-VERIFIED vs FIELD-VERIFIED
- **Background:** Master Context §0 and §25 establish that software tests in CI/CD using mocks only prove software logic, not physical hardware or live carrier integration.
- **Classification Status:**
  - **UNIT-VERIFIED (Automated CI):** 53 test suites (3,386 assertions) covering double-entry ledger balancing, concurrency row locks, period lockouts, cryptographic Ed25519 signatures, XSS sanitization, and permission boundaries.
  - **FIELD-VERIFIED (Physical Equipment Required):**
    1. *POS Thermal Receipt Printing:* ESC/POS serial/USB baud-rate and cutter operations (Bixolon, Sewoo, Epson).
    2. *Biometric Attendance Clocks:* ZKTeco ADMS push protocol across NAT routers and physical devices.
    3. *Iranian SMS Gateways:* Live OTP and alert delivery via Kavenegar/FarazSMS HTTP APIs.
    4. *Bank IPGs:* Shaparak callback verification via Saman/Parsian merchant terminals.
    5. *Barcode Scanners:* 2D DataMatrix scanning latency in WMS companion app.
- **Owner Question 4:** *Do you acknowledge that hardware-dependent and carrier-dependent modules are classified as UNIT-VERIFIED pending the physical testing bench, and approve setting up a physical hardware test bench before production deployment?*

---

### Item 5: Licensing Tiers, "Never Re-Gate" (D-026) & Offline Grace Periods (D-125)
- **Background:** Phase B implemented asymmetric Ed25519 license validation with offline tool `tools/license-issuer/dakhl-license.php`.
- **Policy Invariants:**
  - **D-026 (Never Re-Gate):** `tax_moadian` (سامانه مودیان) is guaranteed in the Basic (برنزی) tier and can never be relegated to high-tier upsells.
  - **Grace Period (D-024):** Upon license expiration, a 14-day non-destructive grace period is granted; after 14 days, the software shifts to read-only mode (viewing and reporting allowed, new document postings blocked).
  - **Offline Key Isolation:** Private signing keys are never bundled with the application distribution.
- **Owner Question 5:** *Do you confirm the final commercial tier breakdown (Basic: Core Accounting, Invoicing, Cheques, Inventory, Moadian; Pro: Multi-POS, Multi-Warehouse, Payroll, E-Commerce Sync; Enterprise: Biometric Attendance, AI Copilot, Guild B2B)?*

---

## 3. OWNER SIGN-OFF MATRIX

| Item # | Topic | Recommended Action | Owner Response | Status |
|---|---|---|---|---|
| **O-01** | Trade Architecture (D-074) | Defer to post-CR-7 as upstream layer | Pending | OPEN |
| **O-02** | Installment Unsettlement | Keep unwired; implement D-127 reversal | Pending | OPEN |
| **O-03** | Clean-Room Distribution Gate | Withhold closed-source builds until CR-7 | Pending | OPEN |
| **O-04** | Field Hardware Test Bench | Establish physical lab before retail rollout | Pending | OPEN |
| **O-05** | Commercial Licensing & Tiers | Ratify Basic/Pro/Enterprise + 14-day grace | Pending | OPEN |
