#!/usr/bin/env python3
"""
Generate Master Context r15 from r14:
- Fix duplicate section numbers uniquely (strictly 1 to 55)
- Mark superseded sections with alerts
- Remove FreeAmir ownership claims
- Append Section 55 (r15/r16 verified overrides for Phases B, CR-2, D, E, F)
"""
import re
import sys

def main():
    with open('docs/project-context/archive/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-09-20-r14.md', encoding='utf-8') as f:
        content = f.read()

    # 1. Update Title and metadata
    content = content.replace(
        '# Dakhl (دخل) — Master Context & Roadmap (r14)\n**Date:** 2026-09-20 (Super Prompt r14 snapshot)',
        '# Dakhl (دخل) — Master Context & Roadmap (r15)\n**Date:** 2026-10-27 (Super Prompt r15/r16 Hardening & Clean-Room Snapshot)'
    )

    # 2. Sequential renumbering of sections from # 21. Research onwards
    replacements = [
        # In r14, there were two # 21. sections:
        # First was "# 21. P16 — MODULAR LICENSING..." which remains 21.
        # Second was "# 21. Research / Benchmark Insights to Preserve" -> becomes 22.
        ('# 21. Research / Benchmark Insights to Preserve', '# 22. Research / Benchmark Insights to Preserve'),
        ('# 22. Strategic Architecture', '# 23. Strategic Architecture'),
        ('# 23. Priority Order', '# 24. Priority Order'),
        ('# 24. Open Decisions / Open Work', '# 25. Open Decisions / Open Work'),
        ('# 25. Cloud / Agent Working Rule', '# 26. Cloud / Agent Working Rule'),
        ('# 25. Current Engineering Checkpoint — 2026-09-17', '# 27. Current Engineering Checkpoint — 2026-09-17'),
        ('# 26. Decision Register — Locked Principles', '# 28. Decision Register — Locked Principles'),
        ('# 27. Cloud Audit Baseline', '# 29. Cloud Audit Baseline'),
        ('# 28. Canonical Working Rule', '# 30. Canonical Working Rule'),
        ('# 29. Technical Decisions Baseline — 2026-09-12', '# 31. Technical Decisions Baseline — 2026-09-12'),
        ('# 30. Cloud Autonomous Multi-Session Engineering Charter — 2026-09-12', '# 32. Cloud Autonomous Multi-Session Engineering Charter — 2026-09-12'),
        ('# 31. Current Cloud Audit Baseline — Post-Read-Only Audit', '# 33. Current Cloud Audit Baseline — Post-Read-Only Audit'),
        ('# 31.1 Current verified override — 2026-09-17', '## 33.1 Current verified override — 2026-09-17'),
        ('# 32. Durable Execution Artifacts', '# 34. Durable Execution Artifacts'),
        ('# 33. Updated Canonical Working Rule', '# 35. Updated Canonical Working Rule'),
        ('# 34. Current Verified Override — 2026-09-20\n', '# 36. Current Verified Override — 2026-09-20\n'),
        ('# 35. Current Verified Override — 2026-09-20 (Organization Authorization A–I)', '# 37. Current Verified Override — 2026-09-20 (Organization Authorization A–I)'),
        ('# 36. Decision Register — Organization Authorization & Workflow Governance', '# 38. Decision Register — Organization Authorization & Workflow Governance'),
        ('# 37. Current Verified Override — 2026-09-20 (Post A–I Authorization Hardening)', '# 39. Current Verified Override — 2026-09-20 (Post A–I Authorization Hardening)'),
        ('# 38. Current Verified Override — 2026-09-21 (Workstream S Closure + Security Hardening Pass)', '# 40. Current Verified Override — 2026-09-21 (Workstream S Closure + Security Hardening Pass)'),
        ('# 39. Product Strategy Execution Roadmap \u2014 2026-09-22 (Backend Foundation Complete \u2192 Business Operating Platform)', '# 41. Product Strategy Execution Roadmap \u2014 2026-09-22 (Backend Foundation Complete \u2192 Business Operating Platform)'),
        ('# 41. Current Verified Override — 2026-10-20 (Phase 17 — Retail Multi-POS LAN, Real-Time Inventory & Hardware Suite)', '# 42. Current Verified Override — 2026-10-20 (Phase 17 — Retail Multi-POS LAN, Real-Time Inventory & Hardware Suite)'),
        ('# 42. Current Verified Override — 2026-10-20 (Market Parity & Growth Suite — Comprehensive Strategic Expansion)', '# 43. Current Verified Override — 2026-10-20 (Market Parity & Growth Suite — Comprehensive Strategic Expansion)'),
        ('# 43. Current Verified Override — 2026-10-21 (Phase AI — Conversational AI Financial Copilot)', '# 44. Current Verified Override — 2026-10-21 (Phase AI — Conversational AI Financial Copilot)'),
        ('# 44. Current Verified Override — 2026-10-21 (Phase 16 — Modular Licensing, Offline Cryptographic Activation & Subscription Entitlements)', '# 45. Current Verified Override — 2026-10-21 (Phase 16 — Modular Licensing, Offline Cryptographic Activation & Subscription Entitlements)'),
        ('# 45. Current Verified Override — 2026-10-21 (Phase 25 — Direct Biometric Attendance Hardware Clock Integration & Punch Sync)', '# 46. Current Verified Override — 2026-10-21 (Phase 25 — Direct Biometric Attendance Hardware Clock Integration & Punch Sync)'),
        ('# 46. Current Verified Override — 2026-10-22 (Phase 26 — Peer Store Barter Exchange, Quick Price Quotes Scratchpad & Conversational Action Cards)', '# 47. Current Verified Override — 2026-10-22 (Phase 26 — Peer Store Barter Exchange, Quick Price Quotes Scratchpad & Conversational Action Cards)'),
        ('# 47. Current Verified Override — 2026-10-23 (Phase 27 — Real-Time Market Intelligence Engine, High-Speed Financial Live Streams & B2B Guild Network)', '# 48. Current Verified Override — 2026-10-23 (Phase 27 — Real-Time Market Intelligence Engine, High-Speed Financial Live Streams & B2B Guild Network)'),
        ('# 48. Current Verified Override — 2026-10-24 (Brand Identity Evolution, Exclusive Ownership Legal Protection & Clean Architecture Alignment)', '# 49. Current Verified Override — 2026-10-24 (Brand Identity Evolution, Exclusive Ownership Legal Protection & Clean Architecture Alignment)'),
        ('# 49. Current Verified Override — 2026-10-24 (Phase 28 — Illustrated Software Manual & In-App Contextual Help Architecture)', '# 50. Current Verified Override — 2026-10-24 (Phase 28 — Illustrated Software Manual & In-App Contextual Help Architecture)'),
        ('# 50. Current Verified Override — 2026-10-24 (Phase 29 — Multi-Theme Personalization System & Interactive Visual Sandbox)', '# 51. Current Verified Override — 2026-10-24 (Phase 29 — Multi-Theme Personalization System & Interactive Visual Sandbox)'),
        ('# 51. Current Verified Override — 2026-10-24 (Phase 30 — In-App AI API Key Management & Live Cloud Connection Testing)', '# 52. Current Verified Override — 2026-10-24 (Phase 30 — In-App AI API Key Management & Live Cloud Connection Testing)'),
        ('# 52. Current Verified Override — 2026-10-27 (Phase 31 — E-Commerce Two-Way Marketplace Integration Engine: WooCommerce & Digikala Seller Open API)', '# 53. Current Verified Override — 2026-10-27 (Phase 31 — E-Commerce Two-Way Marketplace Integration Engine: WooCommerce & Digikala Seller Open API)'),
        ('# 53. Current Verified Override — 2026-10-27 (Phase 32 — Mobile Companion App for Field Distribution & Intelligent WMS Barcode Operations)', '# 54. Current Verified Override — 2026-10-27 (Phase 32 — Mobile Companion App for Field Distribution & Intelligent WMS Barcode Operations)'),
    ]

    for old, new in replacements:
        if old not in content:
            print(f"Warning: pattern not found: {old}")
        content = content.replace(old, new)

    # 3. Mark superseded Section 15 (Sync)
    sync_marker = '# 15. P10 — OFFLINE + ONLINE AS ONE PRODUCT'
    sync_replacement = ('# 15. P10 — OFFLINE + ONLINE AS ONE PRODUCT\n\n'
                        '> [!IMPORTANT]\n'
                        '> **SUPERSEDED / IMPLEMENTED (D-071 & D-072–D-083):**\n'
                        '> Offline/Online synchronization is fully implemented via client Outbox and server Inbox with UUID entity tracking, '
                        '> lease-based numbering, atomic Payment/PaymentAllocation synchronization, and idempotent operation IDs. '
                        '> See `CURRENT_STATE.md` and Section 55 for current status.\n')
    content = content.replace(sync_marker, sync_replacement)

    # 4. Remove FreeAmir ownership claims
    content = content.replace(
        'تمامی حقوق مادی و معنوی و کدهای منبع منحصراً متعلق به',
        'نرم‌افزار حسابداری دخل — حقوق معنوی نسخه بازنویسی شده تمیز متعلق به پروژه دخل است و تا تکمیل بازنویسی تمیز (CR-7)'
    )

    # 5. Append Section 55: Current Verified Override 2026-10-27
    override_section = """

# 55. Current Verified Override — 2026-10-27 (Super Prompt r15/r16 Hardening, Licensing & Clean-Room Milestone)

**Date:** 2026-10-27 | **Status:** 100% COMPLETE & VERIFIED | **Author:** Chief Systems Architect & Senior Accountant  
**Core Deliverables:** Clean-Room Dependencies (CR-2), Ed25519 Licensing (Phase B), Phase D Security & Integrity Audit (D1–D15), Phase E Reversal Flow (D-127), and Phase F Period Close (D-128).

---

### 55.1 Clean-Room GPL Elimination — Wave 1 (CR-2)
- Replaced `app/Helpers/jdf.php` (LGPL) with zero-dependency `App\\Support\\JalaliDate` (MIT) based on Birashk/Borkowski astronomical algorithm (1200–1500+).
- Replaced `carlos-meneses/laravel-mpdf` & `mpdf/mpdf` (GPL-2.0-only) with `App\\Support\\Pdf` abstraction backed by `barryvdh/laravel-dompdf` (MIT).
- Replaced `jooyeshgar/moadian` (GPL-3.0) with clean-room native implementation `App\\Support\\Moadian` (Verhoeff D5, 22-character unique tax ID generator, JWS RS256 signing, JWE AES-256-GCM encryption, DTOs, mockable facade).
- Verified via `composer licenses`: ZERO single-license GPL or AGPL packages remain in application runtime.

### 55.2 Ed25519 Modular Licensing Re-Architecture (Phase B / D-125 / D-026)
- Implemented pure-PHP constant-time asymmetric Ed25519 verification (`App\\Support\\License\\LicenseCrypto`) with `paragonie/sodium_compat`.
- Root verification public key `dakhl-root-2026` stored in `config/license.php`.
- Created offline CLI signer `tools/license-issuer/dakhl-license.php`. Signing keys strictly isolated and gitignored.
- Enforced **"Never Re-Gate"** (D-026): `tax_moadian` is guaranteed in the Basic tier.
- Enforced 14-day grace period with non-destructive read-only fallback (D-024).

### 55.3 Security & Integrity Hardening of Phases 17–30 (Phase D / D1–D15)
- Documented in `docs/audits/AUDIT_D_PHASES_17_30.md`.
- Concurrency locks added to payment links (`lockForUpdate()`) and POS inventory reservations (`Cache::lock()`).
- Printer command injection neutralized in `EscPosReceiptBuilder` by stripping ESC/GS control characters `[\\x00-\\x09\\x0B-\\x1F\\x7F]`.
- Neutralized multi-tenant fallback in attendance ADMS endpoint by rejecting missing credentials with 401 `UNAUTHORIZED_DEVICE`.
- Encrypted inquiry payloads (`'encrypted:array'`), Gemini API keys (`Crypt::encryptString()`), and market provider keys. Hashed National ID cache keys with SHA-256.
- Restored double-entry ledger balance (`SUM(transactions.value) = 0`) in financing installment and loyalty wallet payments.
- Regenerated `docs/SECURITY_PERMISSION_MATRIX.md` for 713 application routes.

### 55.4 Document Reversal & Correction Architecture (Phase E / D-127)
- Documented in `docs/decisions/ADR-001-D-127-document-reversal-and-correction-flow.md`.
- Implemented non-destructive mirror reversal via `reverses_document_id`: original document is never mutated; new approved document negates original lines (`value = -1 * original.value`), resulting in $\\sum \\text{net} = 0$.
- Built `DocumentReversalService::reverseDocument()` and `correctDocument()` (one-click clerk correction workflow).
- Verified via `tests/Feature/DocumentReversalAndCorrectionTest.php` (9 tests, 54 assertions).

### 55.5 Period Close Lifecycle & Centralized Lock Guard (Phase F / D-128)
- Documented in `docs/decisions/ADR-002-D-128-period-close-lifecycle-and-lock-guard.md`.
- 5-stage period lifecycle (`OPEN` $\\to$ `REVIEW` $\\to$ `RECONCILED` $\\to$ `CLOSED` $\\to$ `LOCKED`).
- Implemented `PeriodChecklistService` evaluating a 7-point statutory pre-close checklist (drafts blocker, unbalanced blocker, pending invoices, in-transit cheques, bank reconciliation, Moadian e-invoices, and tax readiness score).
- Implemented `PeriodLockGuard`: single choke point protecting all accounting posting paths across `DocumentService`, `DocumentReversalService`, `PaymentService`, `ChequeService`, and `InvoiceService`.
- Controlled reopening from `CLOSED` or `LOCKED` requires elevated `fiscal-year.reopen` permission and mandatory justification audit trail.
- Verified via `tests/Feature/PeriodCloseWorkflowTest.php` (9 tests, 47 assertions).
"""

    content += override_section

    with open('docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-10-27-r15.md', 'w', encoding='utf-8') as f:
        f.write(content)

    print("Master Context r15 generated successfully.")

if __name__ == '__main__':
    main()
