# ADR-002 / Decision D-128: Period Close Lifecycle & Centralized Lock Guard Architecture
**Title:** Period Close State Machine and Ledger Mutation Guard (چرخه بستن دوره مالی و گارد قفل دفاتر)  
**Status:** APPROVED & LOCKED  
**Date:** 2026-10-27  
**Deciders:** Chief Systems Architect, Senior Accountant, Senior Backend Engineer  
**Context:** Super Prompt r15/r16 Phase F, Master Context §29.10, Decision D-042, Iranian Tax Administration (INTA) Requirements

---

## 1. CONTEXT & PROBLEM STATEMENT

Financial year closing (بستن حساب‌ها و دوره مالی) in Iranian accounting practice requires systematic stages:
1. Reconciling bank balances, verifying petty cash, settling in-transit cheques, clearing pending invoices, and generating income summary documents.
2. Previously, Dakhl only had a basic binary status (`closed_at !== null`), leaving teams without visibility into the closing progress (e.g. audit review, bank reconciliation, tax filing).
3. Furthermore, when a fiscal year is closed or locked for statutory tax audits, financial mutations (posting invoices, inserting documents, editing vouchers, allocating payments, or bouncing cheques) must be unconditionally rejected by a single, authoritative choke-point guard.

---

## 2. DECISION PRINCIPLES & CONTRACT

### 2.1 The 5-Stage Period State Machine (`PeriodStatus`)
Every company/fiscal year transitions through an auditable 5-state lifecycle:
1. **`OPEN` (دوره جاری / باز):** Normal daily operations; all postings and adjustments permitted.
2. **`REVIEW` (درحال انطباق و بررسی):** Financial team review phase; pre-close checklist active; warnings highlighted.
3. **`RECONCILED` (مغایرت‌گیری و تسویه شده):** All bank accounts and sub-ledgers matched; zero unbalanced or draft documents allowed.
4. **`CLOSED` (بسته شده):** Income summary (سند سود و زیان) and final closing entries (سند اختتامیه) posted; opening document generated in successor year. Standard mutations blocked; adjustments require `fiscal-year.closed-period-override`.
5. **`LOCKED` (قفل قطعی):** Final statutory lock (بایگانی قطعی و تسلیم اظهارنامه). Absolutely zero direct financial mutations, edits, deletions, or reversals permitted. Any retroactive adjustment must be recorded as an opening-balance correction in the successor open period.

### 2.2 Pre-Close Checklist (7-Point Statutory Audit)
Before advancing to `CLOSED` or `LOCKED`, the system evaluates seven indicators:
1. **Draft Documents (`draft_documents`):** Count of unapproved documents. (BLOCKER if > 0).
2. **Unbalanced Documents (`unbalanced_documents`):** Count of documents with $\sum \text{value} \ne 0$. (BLOCKER if > 0).
3. **Pending Draft Invoices (`pending_invoices`):** Invoices in draft status. (WARNING).
4. **In-Transit Cheques (`in_transit_cheques`):** Cheques awaiting clearance, deposit, or return. (WARNING).
5. **Unreconciled Bank Lines (`unreconciled_bank_items`):** Bank feed lines without corresponding ledger matches. (WARNING).
6. **Moadian Pending Evidences (`moadian_pending`):** E-invoices awaiting tax authority submission. (WARNING).
7. **Tax Readiness Score (`tax_readiness_score`):** Composite audit percentage (0–100%).

### 2.3 Centralized Period Lock Guard (`PeriodLockGuard`)
Rather than scattering ad-hoc checks across controllers, all financial mutations route through `PeriodLockGuard::assertMutable($companyId, $action)`:
- Choke-points protected:
  - `DocumentService::createDocument()`
  - `DocumentService::updateDocument()`
  - `DocumentService::deleteDocument()`
  - `DocumentService::changeDocumentStatus()`
  - `DocumentReversalService::reverseDocument()`
  - `PaymentService::createCreditPayment()` / `createPayment()`
  - `PaymentService::allocate()`
  - `ChequeService::register()`
- Behavior:
  - If `period_status === LOCKED`: Unconditionally throws `ValidationException`.
  - If `period_status === CLOSED`: Rejects unless caller possesses elevated permission `fiscal-year.closed-period-override`.

### 2.4 Controlled Reopening with Elevated Audit Trail
- Moving backward from `CLOSED` or `LOCKED` requires elevated permission `fiscal-year.reopen`.
- Requires mandatory justification reason recorded in `companies.reopen_reason`, `reopened_at`, and `reopened_by`.
- Activity log event `fiscal_year.period_reopened` is published.
