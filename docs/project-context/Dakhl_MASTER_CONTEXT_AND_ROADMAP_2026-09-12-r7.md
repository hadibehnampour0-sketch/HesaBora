# دخل / Dakhl — Master Context + Roadmap
## Version: 2026-09-12-r7
## Status: Authoritative Project Context

> این فایل مرجع یکپارچه تصمیمات محصول، معماری، جهت‌گیری بازار و رودمپ دخل است. نسخه‌های قبلی حذف نمی‌شوند؛ این نسخه برای ادامه کار مرجع بالاتری است مگر اینکه بعداً صراحتاً اصلاح شود.

---

# 1. Product Identity

**Product name:** دخل / Dakhl

FreeAmir / امیر فقط upstream/base heritage است و برند محصول دخل نیست.

## Product Vision

Dakhl باید از یک نرم‌افزار صرفاً ثبت‌کننده حسابداری به یک **Financial + Tax + Business Operating System برای کسب‌وکار ایرانی** تبدیل شود.

### Core product statement

> دخل فقط ثبت نمی‌کند؛ می‌فهمد، کنترل می‌کند و آماده می‌ماند.

### Core UX statement

> کاربران با زبان کسب‌وکار کار می‌کنند؛ دخل پیچیدگی حسابداری و مالیات را پشت صحنه مدیریت می‌کند.

### Strategic differentiation

هدف ساخت «یک نرم‌افزار حسابداری دیگر» نیست. مزیت باید از ترکیب زیر ایجاد شود:

- Accounting Truth
- Tax Authority / Compliance Truth
- Bank / Payment Truth
- Evidence Truth
- Industry Intelligence
- Simple UX for non-accountants
- Professional depth for accountants
- AI acting through the same trusted services

---

# 2. Non-Negotiable Product Principles

1. **Accounting core is protected.** The existing double-entry spine is not casually rewritten.
2. **One financial truth.** Simple UI, Accountant UI, API, Offline, Online and AI must converge on the same Domain/Service Layer.
3. **Hide complexity, not capability.** Beginner users see business language; professionals can drill down to accounting details.
4. **Offline + Online are first-class from now on.** They are two runtimes of one product, not two independent products.
5. **Tax is continuous.** Tax compliance is built during daily operations, not assembled at year end.
6. **Financial events are immutable/traceable.** Posted data is corrected through controlled reversal/adjustment flows.
7. **No parallel business logic.** API/AI must not implement a second accounting engine.
8. **External data is isolated.** Market-data providers, bank feeds and other integrations must never become dependencies of the accounting core.
9. **Rules are versioned.** Tax rates, thresholds, exemptions and industry rules must be effective-dated data, not hard-coded constants.
10. **Evidence is first-class.** Every important financial/tax number should be traceable to its source documents and supporting evidence.
11. **Financial synchronization is command/operation aware.** Naive last-write-wins is not acceptable for financial events.
12. **Legal claims stay bounded.** Dakhl can aim to produce an audit-ready/defensible file, but must not promise immunity from any lawful tax review or audit.

---

# 3. Current Verified Product Baseline

## Existing / DONE

- Laravel 12 + PHP 8.2+ codebase
- NativePHP desktop packaging / Windows x64 installer path
- Embedded PHP runtime for desktop distribution
- SQLite-based desktop/offline direction
- Authentication
- Roles / permissions
- Customers / Suppliers
- Sales / Purchase / Return invoices
- Payments / Receipts
- Cheques / Guarantee Cheques / Chequebooks
- Accounting Documents + Transactions + balance invariant
- Products / Services / Warehouses / COGS
- Reports: ledger / journal / sub-ledger / trial balance / company overview / cost-income
- HR / Attendance / Payroll / Personnel Requests
- CRM
- Monthly Budgets
- Backup / fiscal-year transfer
- Activity / audit logs
- Persian / RTL / Jalali
- Moadian integration
- Sanctum + API token management infrastructure

## Verified architectural strength

The current accounting spine is:

`Subject -> Document -> Transaction`

The transaction layer is the accounting source of truth for balances; the legacy customer balance field is not the canonical balance source.

## Current release issue

The packaged Windows application reaches the login page, but incorrect username/password can currently produce:

`500 Internal Server Error`

Expected behavior:

- wrong username -> friendly authentication error
- wrong password -> friendly authentication error
- correct credentials -> successful login
- fresh-install regression verified

No PHP/MariaDB installation should be required on the end-user machine for the packaged desktop application.

## Known project engineering status carried forward

The project has a broad feature surface and an extensive test suite, but future work must follow checkpointed, auditable phases rather than giant autonomous changes.

## Cloud Read-Only Audit — 2026-09-12 (Repository-verified findings)

The first full repository audit after agent reset confirmed the following important implementation facts:

- Posted-document / posted-transaction immutability is **not yet enforced in code**; approved records can still be edited or hard-deleted through existing paths.
- Company/document/transaction delete cascades can currently remove financial history; this is an **active data-loss risk** and must receive an interim safeguard before broader expansion.
- PHP floating-point arithmetic is systemic across the service layer (reported at 200+ occurrences across 24 service files); decimal safety is therefore a platform-level prerequisite, not a small cleanup.
- Cheque lifecycle creates additional `Payment` rows across stages; the ledger can remain balanced, but Payment-level/reporting semantics can duplicate business events unless the lifecycle is normalized.
- Cheque bounce currently does not fully reverse the registration-time financial state.
- No `PaymentAllocation`, `CheckSettlement`, Outbox/Inbox, cursor sync, or idempotent financial-sync scaffolding currently exists; these remain greenfield architecture work.
- `TaxSlab` is a payroll withholding model, not a suitable general-purpose tax-rule engine; the future Tax Engine should not simply reuse it as its canonical rules model.
- AR ageing already exists in CRM analytics and should be reused/surfaced for the future general AR reporting layer rather than rebuilt from zero.
- Basic RBAC exists, but company-specific roles are not implemented.
- NativePHP desktop database wiring depends on runtime/environment configuration; migration/seed failures can be silently reduced to logs, so desktop boot/runtime hardening remains important for the Login-500 investigation and future Offline/Online work.

These findings are now part of the project baseline and must be considered when prioritizing work.

---

# 4. Protected Accounting Core

The following are architectural anchors and must not be casually rewritten:

- `DocumentService`
- `Transaction` model/schema
- `InvoiceTransactionBuilder`
- `PaymentService`
- `ChequeService`
- `SubjectService::sumSubject()`
- Company scoping / `DefaultCompany` / `SetApiCompany`
- Spatie permission layer / permission middleware
- Fiscal-year transfer logic
- Existing Moadian integration
- NativePHP packaging/runtime mechanism

Any change here requires focused accounting invariants, regression tests and explicit review.

---

# 5. P0 — RELEASE + ACCOUNTING INTEGRITY HARDENING
## Status: ACTIVE / BLOCKING MAJOR EXPANSION

### 5.0 Immediate financial data-safety containment — NEW
**See D-085 in the Decision Log (2026-09-17): LOCKED, IMPLEMENTED, VERIFIED.** `Company::deleting()` is now provenance-aware -- deletion is permitted only when every financially effective record belongs to copied/imported baseline history (`documents.provenance = BASELINE`, set only by `FiscalYearService::_importDocuments()`) with no native/local activity since (any NATIVE approved Document, or any `PaymentAllocation` at all, blocks deletion). Full suite 1382/1382. These checkboxes may now be revisited against the real, current guard behavior.
- [ ] Prevent deletion of any company containing approved/posted financial data
- [ ] Add an explicit guard before `CompanyController::destroy()` can cascade-delete financial history
- [ ] Review/narrow destructive cascade paths affecting `Document` / `Transaction` history
- [ ] Define and test safe behavior for companies with financial history
- [ ] Preserve existing data; do not rely on a future migration alone to undo a currently-dangerous delete path
- [ ] Add regression tests for attempted destructive deletion of posted financial data

### 5.1 Desktop authentication
**Re-verified 2026-09-17 (Sequential Roadmap Execution Queue, Batch 3) against the actual current repository, not just the earlier "DONE" note in §23 -- confirmed still true, no code change needed.** `LoginController::login()` returns a clean `back()->withErrors(...)` for both wrong username and wrong password (never a raw exception/500), applies Persian-digit normalization (`toEnglish()`) so a Persian-keyboard-typed all-digit password matches, and supports both username and legacy-email login. `NativeAppServiceProvider::boot()` isolates migration/seed/bootstrap failures into a friendly native `Alert::error()` dialog shown before the window even opens -- structurally separate from `LoginController`'s own error path, so an environment/database bootstrap failure can never surface as a bare authentication error or vice versa. The `2026_07_08_000001_convert_enum_columns_to_integer_values` migration (string enum columns -> tinyint) remains in place, the cross-engine (SQLite-desktop/MySQL-web) compatibility fix. Tests: `UsernameLoginTest`/`AuthLifecycleTest` (59 tests, all passing) cover wrong-username/wrong-password/correct-credential/Persian-digit-normalization paths at the Laravel level; the packaged-Electron-runtime fresh-install path itself is not automatable from this environment and remains verified only by the prior manual "confirmed working on a real installed build" check (§23) -- not re-verified here.
- [x] Fix invalid-login 500
- [x] Friendly wrong-username handling
- [x] Friendly wrong-password handling
- [x] Correct-credential success path
- [x] Fresh-install regression -- verified at the bootstrap-isolation/migration level; the actual packaged-build manual check is the one still-unverified-by-this-session part, see note above
- [x] Production error handling verified

### 5.2 Posted financial immutability
- [ ] Enforce approved/posted document immutability in service/controller paths
- [ ] Block direct edit/delete of posted transactions through normal UI/API paths
- [ ] Define controlled reversal/adjustment flows for corrections
- [ ] Add authorization + audit tests for attempted edits/deletes

### 5.3 Cheque/payment integrity
- [ ] Canonical cheque lifecycle semantics
- [ ] Prevent duplicate posting at registration/clear
- [ ] Bounce fully restores invoice/customer state
- [ ] Revisit guarantee-cheque execute semantics
- [ ] Prevent false cash/payment events
- [ ] Ensure corrections do not create duplicate financial movements

### 5.4 Money/posting hardening
- [ ] Inventory all financial float/floating-point calculations across the service layer
- [ ] Replace unsafe float-dependent financial calculations at financial boundaries
- [ ] Define a single decimal-safe money arithmetic strategy
- [ ] Exact decimal-safe arithmetic at financial boundaries
- [ ] Preserve `SUM(transactions.value) = 0` approval invariant
- [ ] Strengthen posted-document immutability
- [ ] Review destructive cascade-delete behavior for financial history

### 5.5 Production security
- [ ] Debugbar development-only
- [ ] Debug state protected in production
- [ ] Sensitive token/activity-log exposure reviewed

---

# 6. P1 — PAYMENT ALLOCATION + BANK RECONCILIATION
## Status: HIGHEST-VALUE ARCHITECTURAL UPGRADE AFTER P0 SAFETY

## 6.1 Payment Allocation

Introduce:

`Payment -> PaymentAllocation -> Invoice`

Allocation fields should support at least:

- amount
- allocated_at
- created_by
- reference

Capabilities:

- [ ] one payment -> multiple invoices
- [ ] multiple payments -> one invoice
- [ ] partial allocation
- [ ] unallocated customer credit
- [ ] credit/refund handling
- [ ] correction without second bank movement
- [ ] full allocation audit history

Rule:

> Payment represents the financial movement. Allocation explains how that movement is applied.

## 6.2 Bank Reconciliation

Target:

`Bank Statement -> Bank Transaction -> Match/Reconcile <-> Payment/Journal -> Transactions`

First version:

- [ ] CSV/Excel import
- [ ] manual matching
- [ ] match history
- [ ] duplicate detection
- [ ] no duplicate financial posting

Later:

- [ ] automatic matching rules
- [ ] optional live bank feeds/APIs

## 6.3 AR/AP

Repository finding: AR ageing already exists in CRM analytics; reuse and generalize it rather than rebuilding the calculation from zero.

- [ ] AR ageing
- [ ] AP ageing
- [ ] customer statement drill-down
- [ ] supplier statement
- [ ] overdue worklists

---

# 7. P2 — CHEQUE SETTLEMENT DOMAIN
## Status: PRODUCT DIFFERENTIATOR

## 7.1 CheckSettlement

**Superseded by D-070 (LOCKED, 2026-09-15) — see the full contract in `Dakhl_DECISION_LOG_2026-09-12-r4.md`. Summary only below; the Decision Log entry is authoritative.**

Target (revised — three independent lifecycles, not one chain):

```
Instrument:  Cheque -> register/deposit/clear/bounce/endorse/return/cancel   (UNCHANGED, not touched by settlement)
Obligation:  SettlementObligation (source_type/source_id polymorphic, original_amount, settled_amount, remaining_amount)
Settlement:  SettlementInstallment (obligation_id, due_date, amount, paid_amount, status) -> real Payment -> PaymentAllocation
```

The earlier `Cheque -> CheckSettlement -> Payment` chain in this file was wrong: it implied settlement is a stage of the cheque's own lifecycle, risking `clear()` being reused/redesigned as the settlement mechanism, which D-070 explicitly forbids (a bounced cheque's obligation can be settled in installments while `Cheque.status` correctly stays `Bounced` forever).

Invariant (unchanged in spirit, now scoped to the obligation, not the cheque):

`SUM(SettlementInstallment.paid_amount for one obligation) <= SettlementObligation.original_amount`

Capabilities:

- [ ] `SettlementObligation`/`SettlementInstallment` models/migrations (polymorphic `source`, so obligations aren't hard-locked to cheques)
- [ ] `SettlementService`: create obligation for a cheque, create an installment plan (schedule only — zero accounting effect), record a real payment against an installment
- [ ] Payment linkage reuses the existing Payment Engine: `PaymentService::createCreditPayment()` for the money-in event, `PaymentAllocation` (now polymorphic: `Invoice` or `SettlementInstallment`) for the allocation ledger — no second/parallel payment or allocation system
- [ ] fully-settled state derived from `remaining_amount === 0`, independent of `Cheque.status`
- [ ] audit history (who/when settled how much of which installment)
- [ ] over-settlement guard (enforced at allocation time via the same ledger as invoice over-allocation)

## 7.2 Guarantee cheque

Target:

`Invoice -> Guarantee Cheque (off-balance) -> real installments/payments -> fully settled -> permitted return`

Rules:

- [ ] guarantee remains off-balance until legitimate settlement/payment conditions
- [ ] every real payment can link to settlement
- [ ] fully settled is explicit/derived
- [ ] return permitted only when rules allow
- [ ] return does not create fake duplicate receipt

## 7.3 Cheque intelligence

- [ ] outstanding cheque exposure
- [ ] expected collections
- [ ] overdue cheques
- [ ] guarantee worklist
- [ ] settlement progress

---

# 8. P3 — ACCOUNTING MATURITY

### 8.1 Accounting Dimensions

Do not overload the Chart of Accounts.

Candidates:

- [ ] Branch
- [ ] Department
- [ ] Project
- [ ] Cost Center
- [ ] Sales Channel

A transaction should answer both:

- Which account was affected?
- Which business dimension generated it?

### 8.2 Payment Terms / Installments

- [ ] terms
- [ ] due schedules
- [ ] installment plans
- [ ] overdue tracking
- [ ] integrate with Payment Allocation

### 8.3 Recurring / Scheduled Operations

- [ ] recurring invoices
- [ ] recurring bills/expenses where justified
- [ ] scheduling
- [ ] duplicate prevention
- [ ] audit trail

### 8.4 Multi-Currency

- [ ] transaction currency
- [ ] base currency
- [ ] rate handling
- [ ] FX difference
- [ ] currency-aware reporting

### 8.5 Fixed Assets

- [ ] asset register
- [ ] acquisition
- [ ] depreciation
- [ ] disposal
- [ ] reporting

---

# 9. P4 — INVENTORY / OPERATIONAL DEPTH

Current inventory/COGS foundation is good; extend gradually.

- [ ] purchase receipt workflow
- [ ] sales delivery workflow
- [ ] stock reservations
- [ ] barcode
- [ ] lot/batch
- [ ] serial tracking
- [ ] inventory valuation
- [ ] slow-moving stock

No parallel inventory accounting engine.

---

# 10. P5 — REPORTING 2.0

Design:

`Dashboard -> Report row -> Source document -> Transaction`

Add:

- [ ] cash-flow view
- [ ] bank reconciliation dashboard
- [ ] ageing dashboards
- [ ] cheque exposure
- [ ] profitability by dimension
- [ ] inventory valuation / slow-moving stock
- [ ] full drill-down

---

# 11. P6 — API PLATFORM
## Status: TOKEN FOUNDATION EXISTS / AUDIT REQUIRED

Existing:

- Laravel Sanctum
- personal access tokens
- token management UI
- `routes/api.php`
- `SetApiCompany` / `api-company`
- API activity logging infrastructure

## 11.1 API Audit
- [ ] enumerate endpoints
- [ ] auth middleware map
- [ ] company scoping
- [ ] permissions
- [ ] validation/errors
- [ ] rate limits
- [ ] pagination/filtering
- [ ] financial POST retry safety
- [ ] idempotency
- [ ] audit logging

## 11.2 API v1

- [ ] `/api/v1/...`
- [ ] revocable tokens
- [ ] token expiry/rotation policy
- [ ] scoped permissions
- [ ] explicit company context
- [ ] structured errors
- [ ] pagination/filter conventions
- [ ] idempotency keys for financial operations
- [ ] webhooks/events
- [ ] OpenAPI

## API architectural rule

`Web UI + API + AI -> same Domain/Service Layer -> Accounting Core`

No parallel financial logic.

---

# 12. P7 — ROLE + WORKFLOW MATURITY

- [ ] company-specific roles
- [ ] workflow approvals
- [ ] approval chains
- [ ] segregation of duties
- [ ] posted-document edit lock / controlled correction
- [ ] approval audit improvements

---

# 13. P8 — ZERO-ACCOUNTING-KNOWLEDGE UX
## Status: STRATEGIC FOUNDATION / MUST INFORM ALL FUTURE UX

The Cloud audit confirms this is not a cosmetic redesign problem: existing workflows still expose accounting complexity and should be simplified through shared services, progressive disclosure and business-language workflows.

## Principle

> Complexity is hidden; capability is not.

### User modes

- [ ] Simple Mode for non-accountants
- [ ] Advanced/Accountant Mode
- [ ] Same underlying business logic and financial truth

### UX mechanisms

- [ ] business-language labels by default
- [ ] wizard-based workflows
- [ ] progressive disclosure
- [ ] smart defaults
- [ ] remembered choices where safe
- [ ] prevent errors before submission
- [ ] explainable accounting decisions
- [ ] explainable tax decisions
- [ ] contextual help/micro-learning
- [ ] consistent empty/error/loading states
- [ ] accessibility
- [ ] UX review for sales, purchase, payment, cheque, expense and tax workflows

### Goal

A user with minimal accounting knowledge should be able to complete daily business work without requiring an accountant beside them.

A professional accountant must still be able to drill down to full journal, transaction, tax and audit detail.

---

# 14. P9 — IRAN TAX COMPLIANCE / TAX OPERATING SYSTEM
## Status: STRATEGIC / HIGH PRIORITY

Goal:

Continuously build a tax-ready, traceable, defensible file instead of assembling tax information at year-end.

## 14.1 Taxpayer Profile + Rule Engine

Repository finding: `TaxSlab` currently serves payroll withholding and must NOT become the canonical general tax-rule model.

- [ ] taxpayer profile by legal/person type
- [ ] activity metadata
- [ ] tax group / eligibility metadata
- [ ] VAT applicability
- [ ] effective-dated tax rules
- [ ] versioned rate/threshold/exemption data
- [ ] legal/source reference for rules

## 14.2 Electronic Invoice / Moadian Layer

- [ ] separate accounting status from tax/system status
- [ ] electronic invoice identifiers/lifecycle
- [ ] required metadata validation
- [ ] system reconciliation
- [ ] submission/response/error history

## 14.3 VAT Engine

- [ ] output VAT
- [ ] input VAT eligibility
- [ ] exempt/zero/standard classifications
- [ ] period reconciliation
- [ ] explainable calculation trail

## 14.4 Income Tax / Article 100

- [ ] taxable income layer
- [ ] tax adjustments separate from accounting profit
- [ ] effective-dated Article 100 rules
- [ ] annual eligibility checker
- [ ] liability forecast

## 14.5 Deductibility + Evidence

- [ ] tax-deductibility classification per expense
- [ ] evidence requirements
- [ ] Evidence Vault
- [ ] missing evidence warnings at transaction time
- [ ] source-document/evidence chain

## 14.6 Tax Risk + Readiness

- [ ] Tax Risk Engine
- [ ] books vs taxpayer-system reconciliation
- [ ] books vs bank/POS reconciliation
- [ ] monthly readiness score
- [ ] exception worklists
- [ ] filing dataset builder
- [ ] audit-ready tax pack

Legal boundary:

> Aim for a continuously complete, explainable, audit-ready file. Do not promise immunity from lawful tax review, audit, sampling or professional review.

---

# 15. P10 — OFFLINE + ONLINE AS ONE PRODUCT
## Status: ARCHITECTURE LOCK / MUST SHAPE ALL FUTURE MODULES

**Superseded/sharpened by D-071 (LOCKED, 2026-09-15) — see the full seven-point contract and locked 2.1–2.8 implementation order in `Dakhl_DECISION_LOG_2026-09-12-r4.md`. Summary only below; the Decision Log entry is authoritative.**

Key sharpenings from D-071 that change how this section should be read:
- "Online Runtime / Server Database" below means the **existing single-tenant deployment** for Phase 2, not the future Organization/Tenant model (D-036, Phase 6) — the sync layer must not hard-assume single-tenancy, but it does not wait for Phase 6 either.
- UUID applies only to entities needing distributed identity (`Customer`, `Product`, `Document`, `Invoice`, `Payment`, `Transaction`, `Subject` to start) — local SQLite/server auto-increment `id` columns are kept as-is; UUID is an added identity column, not a primary-key replacement.
- "Reserved ranges" for offline numbering means a **server-leased range per device**; running out of range while offline produces a `Draft`/`Pending Number` state, never a provisional accounting number that could later be reordered.
- Rollout order is locked: Sync Contract → Device Pairing → UUID/Change Tracking → Outbox/Inbox → Customer/Product pilot → Conflict Resolution Workflow (D-072: detection already exists from 2.5, this stage builds the review/resolve/reconverge back half — Keep Server/Keep Local/Manual Edit only, no generic merge) → Document/Invoice → Payment/Transaction. Do not sync Document/Transaction before the pilot domain has proven the protocol.
- **Stage 2.7 (Document/Invoice) is further sharpened by D-073 (LOCKED, 2026-09-15)**: `Document` is the sync aggregate root, `Invoice` is dependent data (no Invoice sync without an accepted parent Document); Sync state and Accounting state are fully separate — Draft syncs freely offline, Approved/Posted follows much stricter rules and a server-rejected approved document is a visible conflict, never a silent rewrite; official document numbers commit at finalization/issuance, never at Draft creation (first real use of D-071's server-leased reserved-range rule); D-072's KEEP_SERVER/KEEP_LOCAL/MANUAL_EDIT model applies only to pre-Posting conflicts, post-Posting policy is deferred to 2.8; hard DELETE is removed from sync for financial documents — only Void/Cancel/Reverse may travel; sync is dependency-aware (an Invoice referencing a not-yet-accepted Customer becomes `Pending Dependency`, not a dropped record or a failed batch). 2.7 itself is staged 2.7A Document Draft → 2.7B Invoice Draft → 2.7C Finalize/Numbering → 2.7D Approved Document → 2.7E Conflict/Dependency/Retry hardening, with Payment/Transaction/Posting/Cheque explicitly deferred to 2.8.

Dakhl has ONE domain and TWO runtimes.

```text
                    Dakhl
                        |
                Shared Domain Layer
                        |
          +-------------+-------------+
          |                           |
     Offline Runtime             Online Runtime
          |                           |
 NativePHP/Electron               Web
          |                           |
      Local SQLite             Server Database
          |                           |
          +---------- Sync -----------+
```

Repository finding: no Outbox/Inbox/cursor/idempotency synchronization scaffolding is currently implemented. Treat this as a greenfield architecture task, not a partial feature extension.

## Offline requirements

- [ ] normal operations without internet
- [ ] local SQLite
- [ ] local auth/session behavior appropriate to offline mode
- [ ] outbox
- [ ] retry-safe operations
- [ ] cached external data with freshness timestamps

## Online requirements

- [ ] browser access
- [ ] same business behavior
- [ ] server-side persistence
- [ ] multi-device support

## Sync architecture

Sync is NOT database replication.

Target:

`Local Business Command -> Local Commit -> Outbox -> Sync API -> Server Validation/Idempotency -> Domain Service -> Server Commit -> Cursor/Ack`

and:

`Server Change Log -> Cursor -> Pull -> Validate -> Apply Local Transaction -> Advance Cursor`

### Financial sync rules

- [ ] business-operation/command based sync
- [ ] outbox/inbox
- [ ] cursor-based pull
- [ ] idempotency
- [ ] retry safety
- [ ] operation-specific conflict policy
- [ ] reconciliation after reconnect
- [ ] no duplicate accounting posting
- [ ] auditability
- [ ] company isolation

### Conflict rules

- master data: may use merge/review policies
- drafts: may merge/review
- posted documents: immutable
- financial events: idempotent/append-only where appropriate
- sensitive approvals: server-authoritative when necessary
- naive last-write-wins: NOT sufficient for financial events

### Architectural consequence

Offline/Online support must be considered when designing Tax, API, AI, Market Data, POS, Mobile and future modules.

---

# 16. P11 — INDUSTRY INTELLIGENCE / MARKET DATA
## Status: FUTURE / STRATEGIC DIFFERENTIATOR

Goal:

Make Dakhl a daily business cockpit, not merely an accounting application.

## 16.1 Industry Profile

Examples:

- gold shops
- steel/iron sellers
- construction materials
- retail
- auto parts
- other industry profiles

When the user selects an industry, relevant widgets/tools become available automatically.

## 16.2 Market Data Layer

Target:

```text
Industry Profile
      |
Market Data Layer
      |
+-----+----------------+
|                      |
Managed Provider    Custom/BYO API
|                      |
+----------+-----------+
           |
    Provider Adapter
           |
    Normalized MarketQuote
```

### Managed Provider

- [ ] ready-to-use provider(s)
- [ ] no technical setup for normal users
- [ ] provider health/freshness
- [ ] fallback provider

### Bring Your Own API

- [ ] custom endpoint/provider
- [ ] API key/secret support
- [ ] encrypted credential storage
- [ ] connection test
- [ ] symbol/instrument mapping
- [ ] refresh configuration
- [ ] provider-specific adapter

### Normalized market data

At minimum:

- instrument
- price
- unit
- currency
- timestamp
- provider
- freshness/status

### Offline market data

- [ ] local cache
- [ ] timestamp/freshness shown
- [ ] never present stale data as live
- [ ] provider failure must never break accounting

## 16.3 Industry tools

Examples:

- live price widgets
- price history
- price alerts
- market vs own price
- margin intelligence
- inventory valuation using market snapshots
- industry calculators

Important rule:

> Market price may inform/suggest; it must not silently rewrite historical or posted financial documents.

When a market price influences a transaction, store a timestamped snapshot/provenance where relevant.

---

# 17. P12 — FINANCIAL INTELLIGENCE / DAKHL COPILOT
## Status: FUTURE / DEPENDS ON TRUSTED CORE

AI is NOT a chatbot bolted onto the UI.

AI is a controlled action/explanation layer on top of the common Domain/Service Layer.

Capabilities:

- [ ] natural-language business Q&A
- [ ] natural-language transaction entry
- [ ] invoice creation from natural language
- [ ] receipt/expense extraction from image/PDF
- [ ] AI-assisted classification
- [ ] AI-assisted payment allocation
- [ ] AI-assisted bank reconciliation
- [ ] anomaly detection
- [ ] cash forecasting
- [ ] tax forecasting
- [ ] tax-risk explanations

### AI safety/architecture rules

- same permissions as a human user
- same Domain/Service Layer
- no direct parallel accounting logic
- consequential financial actions require appropriate user confirmation
- every recommendation has reason/evidence/confidence
- every executed action has audit trail
- AI outputs must be grounded in actual company data

---

# 18. P13 — EVIDENCE GRAPH + AUDIT-READY FILE
## Status: STRATEGIC / CORE TAX DIFFERENTIATOR

Target relationships:

`Invoice <-> Payment <-> Bank <-> Cheque <-> Document <-> Transaction <-> Tax Record <-> Evidence`

Every important number should be traceable back to source evidence.

## Evidence Vault

- [ ] attachments
- [ ] invoices/contracts/receipts/delivery evidence
- [ ] tax-system identifiers
- [ ] bank evidence
- [ ] relation graph
- [ ] immutable history

## Audit-ready file

- [ ] sales
- [ ] purchases
- [ ] expenses
- [ ] VAT
- [ ] payments
- [ ] bank
- [ ] cheques
- [ ] payroll
- [ ] fixed assets
- [ ] tax calculations
- [ ] reconciliations
- [ ] supporting evidence

---

# 19. P14 — TAX READINESS + BUSINESS COCKPIT
## Status: FUTURE

The main dashboard should answer business questions, not expose accounting terminology by default.

Examples:

- How much did I sell today?
- Who owes me money?
- Who do I owe?
- How much cash do I have?
- What payments are overdue?
- What is my tax status?
- What needs attention today?
- Is my bank reconciled?
- Are my invoices/tax records synchronized?
- What is the business risk right now?

## Tax Readiness example

```text
Tax Readiness 91%

Sales             100%
Purchases          98%
VAT                96%
Bank               87%
Evidence           82%

7 mismatches
3 missing evidences
2 high-risk items
```

---

# 20. P15 — QUALITY ENGINEERING
## Status: CONTINUOUS

Every major financial feature needs:

- unit tests
- feature tests
- accounting invariants
- authorization tests
- offline tests where applicable
- sync retry/idempotency tests where applicable
- fresh-install tests
- end-to-end workflows

Required end-to-end scenarios include:

- [ ] invoice -> payment -> allocation -> reconciliation
- [ ] invoice -> guarantee cheque -> installments -> return
- [ ] purchase -> VAT -> tax classification -> tax reconciliation
- [ ] offline transaction -> reconnect -> sync -> server confirmation -> local acknowledgement
- [ ] market provider -> snapshot -> suggested price -> invoice (historical snapshot preserved)
- [ ] AI suggestion -> user approval -> service execution -> audit record
- [ ] backup round trip
- [ ] fiscal-year transfer
- [ ] fresh Windows install
- [ ] packaged embedded PHP runtime

---

# 21. P16 — MODULAR LICENSING / SUBSCRIPTION / ENTITLEMENT PLATFORM
## Status: STRATEGIC / COMMERCIAL ARCHITECTURE LOCK

Goal:
Sell Dakhl as a modular product where customers activate only the capabilities they need, while preserving one shared financial truth and one codebase. Sales, Inventory, Tax, Moadian, CRM, HR/Payroll, Market Intelligence and AI may be sold as independently entitled modules where commercially justified.

## 21.1 Product model

Dakhl should use:

`Product -> Plan -> Subscription -> Entitlement -> Module/Feature -> License/Activation`

Important distinction:
- Billing/Subscription answers what the customer purchased.
- Entitlement answers what capabilities the customer is allowed to use.
- License/Activation answers where/how the entitlement is activated.
- Serial/license key is an identifier or activation credential, not the complete source of authorization.

## 21.2 Modular commercial packaging

The customer should be able to buy modules independently, for example:
- [ ] Core Accounting
- [ ] Sales
- [ ] Purchase
- [ ] Inventory
- [ ] Cheques
- [ ] Tax
- [ ] Moadian
- [ ] CRM
- [ ] HR / Payroll
- [ ] Market / Industry Intelligence
- [ ] AI / Copilot

Plans may support:
- [ ] monthly subscription
- [ ] yearly subscription
- [ ] longer-term subscription where commercially justified
- [ ] trial/temporary entitlement where appropriate

## 21.3 Licensing / entitlement model

Target concepts:
- [ ] organization/company scoped entitlements
- [ ] module-level activation
- [ ] feature flags tied to entitlements
- [ ] device activation limits
- [ ] activation/deactivation flow
- [ ] expiry and renewal
- [ ] revoke/suspend capability
- [ ] grace period for offline use
- [ ] entitlement state cached locally
- [ ] no deletion of customer data when a module expires
- [ ] read-only/limited behavior where appropriate after expiry
- [ ] reactivation restores access to existing data

## 21.4 Offline + Online licensing

Online:
`Subscription -> Entitlement -> License/Activation Server -> Device/Account`

Offline:
`Signed Entitlement/License -> Local Verification -> Local Module Access`

Requirements:
- [ ] signed licenses/entitlements for offline verification
- [ ] secure local storage
- [ ] periodic online refresh when available
- [ ] explicit offline grace policy
- [ ] device transfer/deactivation support
- [ ] server-side revocation/renewal when connected
- [ ] no mandatory always-online dependency for normal desktop work

## 21.5 Security

- [ ] cryptographically verifiable signed license/entitlement payloads
- [ ] encrypted credential/secret storage
- [ ] no sensitive licensing secrets in normal logs
- [ ] activation abuse protection
- [ ] company/user/device isolation
- [ ] audit trail for entitlement/activation changes

## 21.6 Architecture rule

Licensing/Entitlement must gate access to modules and features, but must NOT duplicate accounting/business logic.

Target:

`Shared Domain/Service Layer`

with authorization checks based on:

`User Permission + Company Scope + Entitlement`

## 21.7 Industry-aware bundles

Industry Profile may recommend or activate a suitable bundle, subject to purchase/entitlement.

Example:

Gold shop:
`Core + Sales + Inventory + Tax + Moadian + Gold Market Intelligence`

Steel/iron seller:
`Core + Sales + Inventory + Tax + Moadian + Steel Market Intelligence`

The Industry Profile must never silently grant paid capabilities without a valid entitlement.

## 21.8 Architectural references

Use external projects as inspiration only:
- Keysmith — signed entitlements, offline verification, device activation
- Susi — desktop/offline licensing, leases, grace periods
- Lago — subscriptions, plans, entitlements and billing concepts

Do not copy code or licenses without explicit legal/licensing review.

---

# 21. Research / Benchmark Insights to Preserve

## Strong existing Dakhl advantages

- strong double-entry core
- derived balances from transactions
- Persian/RTL/Jalali localization
- Moadian integration
- desktop/offline distribution direction
- broad SMB ERP feature coverage

## High-value benchmark lessons

From accounting/ERP/open-source ecosystems, preserve the following ideas:

- Vyapar-like business-first simplicity
- Tally-like decision-oriented dashboarding
- Wave-like contextual guidance
- Odoo-like onboarding/localization
- Zoho-like action-oriented AI
- Xero-like explainable reconciliation assistance
- OpenBooks/Hisaabo-like shared service layer for API/AI/UI
- TaxHacker/Acta-like document ingestion and review
- Actual Budget-like local-first philosophy
- Tickabox-like Laravel/NativePHP + outbox/sync patterns
- OpenBB/OpenBull-like provider abstraction/adapters

These are inspiration/architecture patterns only. Do not copy incompatible or restricted code/licensed components without an explicit legal review.

---

# 22. Strategic Architecture

```text
                         Dakhl
                            |
                 +----------+----------+
                 | Shared Domain/Core  |
                 +----------+----------+
                            |
      +---------------------+----------------------+
      |                     |                      |
      v                     v                      v
 Accounting Core        Tax Core            Reconciliation
      |                     |                      |
      +---------------------+----------------------+
                            |
                    Evidence / Audit
                            |
                 +----------+----------+
                 |                      |
           Offline Runtime       Online Runtime
           NativePHP/SQLite       Web/Server DB
                 |                      |
                 +---------- Sync ------+
                            |
              +-------------+-------------+
              |             |             |
             API           AI      Industry Data
```

The accounting spine remains:

`Subject -> Document -> Transaction`

The next-generation separation is:

`Financial Event -> Payment/Bank Transaction -> Allocation/Reconciliation -> Invoice/Cheque/Customer State`

---

# 23. Priority Order

## Updated 2026-09-14 (post-rebrand, post-competitor-sweep) — see §28 for the reasoning behind this reordering

### Phase 0 — DONE this session, pending only a git commit (blocked on git identity, see CLOUD_AUTONOMOUS_STATE.md §0)
0. Immediate financial data-loss containment (company-deletion guard) — **code complete, tested, uncommitted**
0. Posted financial immutability (manual Documents screen guard) — **code complete, tested, uncommitted**
0. Desktop invalid-login 500 + release stability — **root-caused and fixed (SQLite enum migration, username-based auth, Persian-digit normalization); confirmed working on a real installed build**
0. Money/decimal safety (float-epsilon tolerance on the balance invariant) — **code complete, tested, uncommitted**
0. Cheque/payment accounting integrity (bounce restores invoice/payment state) — **code complete, tested, uncommitted**
0. Rebrand HesaBora → Dakhl (D-054) — **executed project-wide, 1161/1161 tests passing, uncommitted**

None of the above needs re-doing. The single next action for all of it is unblocking git identity and landing Commits 1–18 (+ the rebrand diff) from CLOUD_AUTONOMOUS_STATE.md in order.

### Phase 1 — Core financial completeness (next up; protects "one financial truth" before adding surface area)
1. **Payment Allocation**
2. **Bank Reconciliation**
3. **AR/AP ageing + drill-down** (reuse existing CRM ageing calculation)
4. **CheckSettlement / guarantee-cheque correctness**
5. Small cleanups already flagged, cheap to fold in: consolidate the duplicated `applyInvoiceFilters()` (`InvoiceController` vs `ReportExportService`); finish the NativePHP packaging fix (`7zip-bin` dependency — confirmed present, just needs the commit per §8b of the state file)

### Phase 2 — Offline/Online architecture foundation (elevated from #10 — see §28 for why)
6. **Offline/Online architecture validation + sync foundation** (D-032: local-first + server-authoritative, UUID identity, Outbox/Inbox, idempotent operation IDs). This is Dakhl's single most validated differentiator — the 2026-09-14 competitor sweep confirmed **11 of 11** researched Iranian competitors sell two separate products (desktop + "cloud") under one brand instead of one shared engine (D-068). It stays architecturally foundational (per the original note below) but is moved up because Phase 5's e-commerce/CRM/mobile work and Phase 1's own reporting should be built against the real sync model, not retrofitted onto it later.

### Phase 3 — Zero-accounting-knowledge UX + market-validated UI investment (cheap relative to Phase 2, directly targets competitors' most-cited weakness: support/onboarding quality)
7. **Zero-Accounting-Knowledge UX foundation**
8. In-app onboarding (D-051: Driver.js guided tours + progressive disclosure for advanced fields — MIT-licensed, fits the existing Blade stack)
9. Dashboard UI direction (D-057/D-059: top icon+F-key quick-access toolbar, income/expense pie chart reusing the logo palette, dual-series income/expense trend line, RTL grouped sidebar, "today at a glance" cards, customizable report-column picker)
10. Fast barcode-scan checkout screen (D-058: dedicated POS-style sale entry, distinct from the standard invoice form)

### Phase 4 — Tax compliance depth
11. **Tax Profile + Rule Engine + Moadian/VAT compliance foundation**
12. **Evidence + Tax Readiness + reconciliation layer**
13. Accounting Dimensions + Payment Terms/Installments + Recurring

### Phase 5 — Market-facing growth (validated directly against real competitor gaps, see §28)
14. **API audit + API v1**
15. First-party WooCommerce plugin (D-049/D-064: WordPress-side plugin as the primary deliverable, not just API docs — now confirmed as baseline-expected, not a differentiator, since Moein/Helo/Chortke/Chahaar Bagh already have store plugins)
16. CRM satellite schema + sales-rep/marketer commission tracking (D-050, D-056)
17. Invoice-level payment-gateway integration — **new candidate item, not yet scoped** (D-062: inspired by Abarestan's IDPay-based per-invoice payment link + e-wallet + auto-reconciliation; no other tracked competitor has this; needs its own design pass, likely intersecting with Phase 1's Payment Allocation and Phase 5's API work)
18. Mobile companion app pilot (D-048: NativePHP Mobile first, Flutter/Invoice-Ninja-pattern as fallback)

### Phase 6 — Platform / monetization (Licensing is explicitly gated on an open decision, see below)
19. Industry Intelligence / vertical editions (D-038, now with a real-world proof point: Moein's 10+ industry-specific editions on one platform, D-060)
20. Market Data provider abstraction (Managed + BYO)
21. **Modular Licensing + Subscription + Entitlement + Activation — BLOCKED on D-053 (product-tier segmentation).** Do not start detailed licensing design until the owner decides whether Dakhl needs an explicit ultra-simple micro-merchant tier (System Group's Dasht→Sepidar→Rahkaran pattern is the reference point) — the tier model materially shapes the Plan/Entitlement design.
22. Financial Intelligence / Copilot / AI actions

### Phase 7 — Longer tail
23. Multi-currency / Fixed Assets / deeper Inventory
24. Workflow/ecosystem expansion
25. FreeAmir upstream diff (D-052) — not a phase, an ongoing habit: periodically diff HEAD against `Jooyeshgar/FreeAmir` for mergeable upstream work; not yet done even once.

Note: Offline/Online architectural decisions are not a late feature. They must inform implementation of every item from this point forward, even where sync UI itself is delivered later — this is why Phase 2 was moved ahead of the UX and tax-depth phases rather than left at its original position.

---

# 24. Open Decisions / Open Work

## Updated 2026-09-14 — see §28 for full sourcing

### RESOLVED this session (code complete, pending only the git-identity unblock + commit)
- ~~Company financial-data deletion guard~~ — DONE
- ~~Posted-document immutability enforcement (manual Documents screen)~~ — DONE, scoped (see D-028 caveat: service-layer-internal correction flows deliberately left unguarded)
- ~~Systemic decimal/float remediation~~ — DONE for the approval invariant + warehouse inventory filter (tolerance-based fix); broader float sweep across the other ~24 flagged files is still open, not attempted
- ~~Desktop DB boot/runtime root-cause verification for Login-500~~ — DONE (SQLite enum-migration bug + username auth + Persian-digit normalization; confirmed fixed on a real installed build)
- ~~Product rebrand (D-054)~~ — DONE, executed project-wide

### Newly OPEN, owner decision required before dependent work starts
- **Product-tier segmentation (D-053)** — BLOCKS detailed Licensing (Phase 6) design. Needs an explicit owner call: does Dakhl need an ultra-simple micro-merchant tier alongside the main product, the way System Group runs Dasht→Sepidar→Rahkaran on one shared front-end?
- **`dakhl.ir` is unavailable; `dakhl.com`/`dakhl.app` were open at check time and should be secured** — not a product decision, but blocks finalizing `config/nativephp.php`'s placeholder website default and any public-facing marketing.
- **IRIPO (Iran trademark registry) check for "دخل"** — not verifiable via web search; needs a manual filing/search before public launch or marketing spend.

### Existing open work (carried forward, still open)
- ~~`SettlementService::unsettleInstallment()` disposition (D-090)~~ — **RESOLVED 2026-09-18 (Final Marathon Closure Pass)**: now delegates to `PaymentService::removeAllocation()`, a mechanical application of D-084's already-decided rule (never a sanctioned Settlement exception). See Decision Log's "Final Marathon Closure Pass" section. A real, governed Settlement reversal capability remains a future, separate architecture decision if ever wanted.
- CheckSettlement / guarantee-cheque correctness: OPEN
- Multi-invoice payment allocation: OPEN
- ~~Bank reconciliation~~ — DONE (re-verified 2026-09-17, Batch 5): `BankReconciliationService` is a real, complete "first version" (CSV import, dedupe-hash duplicate detection, candidate-transaction ranking, manual match/unmatch, own docblock confirms it never posts a new accounting Document/Transaction, only links an already-posted one). Fully wired: `BankReconciliationController` + `bank-reconciliation.*` routes + seeded permissions + `tests/Feature/BankReconciliationTest.php` (8 tests, all passing). Deliberately out of Sync's `SYNCABLE_ENTITIES` (an import-at-point-of-use action, not a distributed entity) -- correct scope, not a gap. This checklist item was stale; the actual repository state was already complete.
- Offline/Online synchronization architecture (D-032 direction locked, implementation not started): OPEN — see Phase 2
- API endpoint audit: OPEN
- API v1: OPEN
- First-party WooCommerce plugin (D-049/D-064): OPEN — re-scoped from "nice differentiator" to "market-expected baseline" per the competitor sweep
- Tax Compliance foundation: OPEN
- Evidence / Tax Readiness: OPEN
- Zero-accounting-knowledge UX: OPEN
- In-app onboarding (D-051): OPEN, not started
- Dashboard/reporting UI redesign (D-057/D-059): OPEN, not started
- Fast barcode POS checkout screen (D-058): OPEN, not started
- Industry Profile / Market Data platform: OPEN (now with a real-world reference point, D-060)
- Managed Provider + BYO API: OPEN
- Financial/TAX Copilot: OPEN
- General Tax Rule Engine vs payroll `TaxSlab` separation: OPEN (direction confirmed: do not reuse `TaxSlab` as canonical tax engine)
- Modular Licensing / Subscription / Entitlement architecture: OPEN, blocked on D-053
- Module-specific serial/license activation: OPEN
- Offline license validation / grace policy: OPEN
- Company-specific roles: OPEN
- Workflow approvals: OPEN
- CRM satellite schema + marketer/referral commission (D-050/D-056): OPEN, not started
- Mobile companion app pilot (D-048): OPEN, not started
- Invoice-level payment-gateway integration (D-062, new candidate item): OPEN, not scoped
- FreeAmir upstream diff (D-052): OPEN, never attempted even once

---

# 25. Cloud / Agent Working Rule

All coding agents (Cloud/OpenCode/etc.) must use small, checkpointed phases.

For any task involving protected core, Tax, Sync, API, AI or financial logic:

1. Read repository + authoritative context.
2. Produce a concrete plan and identify protected files.
3. Make the smallest coherent change.
4. Run focused tests.
5. Report results and stop at the checkpoint.
6. Do not expand scope without explicit authorization.

Read-only audit tasks must remain read-only.

Before any major new product idea, complete P0 and establish enough P1 foundation to ensure the new idea will not create architectural debt.

---

# 26. Decision Register — Locked Principles

| ID | Decision | Status |
|---|---|---|
| D-001 | Dakhl is the product brand; FreeAmir is upstream/base only | LOCKED |
| D-002 | Existing double-entry accounting spine is protected; no casual rewrite | LOCKED |
| D-003 | One financial truth for Web/Offline/Online/API/AI | LOCKED |
| D-004 | User can operate without accounting knowledge; Simple + Advanced modes | LOCKED |
| D-005 | Complexity hidden, capability preserved | LOCKED |
| D-006 | Tax compliance is continuous, not year-end only | LOCKED |
| D-007 | Tax rules are versioned/effective-dated data | LOCKED |
| D-008 | Audit-ready file is the target; no promise of immunity from lawful audit | LOCKED |
| D-009 | Payment and allocation are separate concepts | LOCKED |
| D-010 | Bank statement and accounting events are separate layers reconciled together | LOCKED |
| D-011 | CheckSettlement is the target abstraction for installment cheque settlement | LOCKED DIRECTION |
| D-012 | Offline + Online are first-class runtimes of one product | LOCKED |
| D-013 | Financial sync is operation-aware; no naive LWW for financial events | LOCKED |
| D-014 | Market data must be provider-abstracted and isolated from accounting core | LOCKED |
| D-015 | Market data supports both Managed Provider and Bring Your Own API | LOCKED |
| D-016 | External market data is cached with freshness/provenance and must not alter posted history | LOCKED |
| D-017 | API and AI use the same Domain/Service Layer | LOCKED |
| D-018 | AI actions are permission-aware, explainable and auditable | LOCKED |
| D-019 | Industry Profile activates industry-specific business intelligence | LOCKED |
| D-020 | Product should become a daily Business/Financial Cockpit, not only accounting software | LOCKED |
| D-021 | Dakhl modules are independently sellable/activatable where commercially justified | LOCKED DIRECTION |
| D-022 | Separate Subscription/Billing, Entitlement, and License/Activation concepts | LOCKED |
| D-023 | Serial/license key is not the full authorization model; entitlement is the authority | LOCKED |
| D-024 | Module expiry must not delete customer data; access may become limited/read-only | LOCKED |
| D-025 | Offline licensing must support signed local verification plus online refresh/revocation | LOCKED DIRECTION |
| D-026 | Industry bundles may recommend paid modules but must respect explicit entitlements | LOCKED |
| D-027 | Destructive deletion of approved financial history must be blocked; financial history protection is P0 | LOCKED DIRECTION |
| D-028 | Posted financial records must be protected by code-level immutability, not documentation alone | LOCKED DIRECTION |
| D-029 | General Tax Rule Engine must remain distinct from payroll `TaxSlab` | LOCKED DIRECTION |
| D-030 | AR ageing logic already present in CRM should be reused/generalized for reporting | LOCKED DIRECTION |
| D-031 | Offline/Online sync is greenfield and must be designed before depending modules are expanded | LOCKED DIRECTION |
| D-032 | Offline/Online uses local-first operation with server-authoritative reconciliation after sync | LOCKED DIRECTION |
| D-033 | Organization/Tenant is the commercial subscription owner; devices are activation limits rather than default billing units | LOCKED DIRECTION |
| D-034 | Subscription/Billing, Entitlement, and License/Activation remain separate concepts | LOCKED |
| D-035 | Entitlement is authorization authority; serial/license key is not sufficient authorization | LOCKED |
| D-036 | Initial SaaS architecture is shared application/database with strict tenant isolation; enterprise database isolation may be offered later | LOCKED DIRECTION |
| D-037 | Tax Governance uses versioned/effective-dated Tax Rule Packs; payroll TaxSlab remains separate | LOCKED |
| D-038 | Industry capabilities extend the Core Domain without forking the accounting engine | LOCKED DIRECTION |
| D-039 | Market data uses Provider/Adapter abstraction and supports Managed Provider + BYO API | LOCKED |
| D-040 | AI uses an AI Gateway/Policy layer and must minimize data sent to external providers | LOCKED DIRECTION |
| D-041 | Dakhl provides first-class versioned data portability packages with staged import | LOCKED DIRECTION |
| D-042 | Mature accounting includes explicit period-close semantics and controlled reopening | LOCKED DIRECTION |
| D-043 | Multi-session autonomous Cloud execution uses durable state + execution log + Git checkpoints | LOCKED DIRECTION |
| D-044 | Cloud may create small implementation sub-phases consistent with locked architecture | LOCKED DIRECTION |
| D-045 | Context/token resets must resume from durable state, not restart completed work | LOCKED DIRECTION |
| D-046 | Routine implementation decisions may be made autonomously; locked product architecture may not be silently changed | LOCKED DIRECTION |
| D-047 | Current Cloud audit findings are authoritative near-term implementation baseline | LOCKED DIRECTION |
| D-048 | Mobile: pilot NativePHP Mobile first (same Domain/Service layer as desktop); Flutter/Invoice-Ninja pattern is the fallback | LOCKED DIRECTION |
| D-049 | E-commerce: first-party WordPress/WooCommerce plugin is the primary deliverable, not just API docs; dedup on WooCommerce order ID via the same Outbox/Inbox idempotency mechanism as D-032 | LOCKED DIRECTION |
| D-050 | CRM: `crm_leads`/`crm_pipelines`/`crm_pipeline_stages`/`crm_activities` satellite schema, nullable-FK-to-Subject on conversion (Odoo-style), no new identity model | LOCKED DIRECTION |
| D-051 | Onboarding: Driver.js (MIT) guided tours + progressive disclosure for advanced fields | LOCKED DIRECTION |
| D-052 | Periodically diff HEAD against upstream FreeAmir for mergeable work | ACTION ITEM |
| D-053 | Product-tier segmentation (micro/SMB/enterprise) | OPEN — owner decision required, blocks Licensing (Phase 6) |
| D-054 | Product renamed HesaBora/حسابورا → Dakhl/دخل, project-wide, all case variants | LOCKED, EXECUTED |
| D-055 | Competitor tracked: چالاک‌حساب (Chalak Hesab) — desktop-only, 3-tier perpetual license | LOGGED |
| D-056 | New feature: sales-rep/marketer commission tracking, designed alongside D-050 | ADDED TO ROADMAP, not scheduled |
| D-057 | Dashboard UI direction: top icon toolbar, income/expense pie chart, dual-series trend line, RTL grouped sidebar, today-at-a-glance cards | LOCKED DIRECTION (visual language) |
| D-058 | Fast barcode-scan POS checkout screen, separate from the standard invoice form | LOCKED DIRECTION |
| D-059 | UI detail: F-key shortcuts on toolbar icons; user-configurable report column picker; inline export controls; persistent status bar | ADDED to D-057 |
| D-060 | Competitor tracked: معین (Moein) — 10+ industry-vertical editions on one platform, real-world validation of D-038 | LOGGED |
| D-061 | Competitor tracked: قیاس (Qiyas) — multi-deployment ecosystem (not shared architecture); POS-integrated Moadian submission | LOGGED |
| D-062 | Competitor tracked: ابرستان (Abarestan) — IDPay-based per-invoice payment link + e-wallet + auto-reconciliation; new candidate roadmap item, not scoped | LOGGED, candidate feature |
| D-063 | Corrections: "Charkeh" is not a real product (blog misattribution of Chortke); "OpenPOS" is a closed, ionCube-obfuscated commercial plugin, not open-source, not usable as D-058 prior art | CORRECTION |
| D-064 | Competitor tracked: چرتکه (Chortke) — confirmed real WooCommerce/webzi store-connection plugin | LOGGED |
| D-065 | Competitor tracked: برهان سیستم پاسارگاد — enterprise/holding market segment (consolidated multi-company reporting), not SME | LOGGED |
| D-066 | Competitor tracked: کسبه (Kasbe) — bank POS card-reader terminal as its own software platform | LOGGED |
| D-067 | Correction: Robot/Reboot's "touch-screen" claim is compatibility marketing, not a real touch-optimized UI — not a valid D-058 reference | CORRECTION |
| D-068 | Cross-cutting: "integrated cloud+desktop" almost always means two separately-coded products under one brand — confirmed across 11/11 researched competitors; strongest available market validation for D-032/D-036 | CONFIRMED PATTERN |
| D-069 | Caution: زروان (Zarvan) identity is ambiguous (3 unrelated things share the name) — not added to tracked competitor list pending direct verification | CAUTION, NOT LOGGED |
| D-070 | CheckSettlement Accounting Design Contract: Instrument (Cheque lifecycle)/Obligation/Settlement are three independent lifecycles; `clear()` is never redesigned/reused for settlement; only a real Payment allocation creates an accounting effect, via the same (now-polymorphic) PaymentAllocation ledger already built for invoices — no second payment/allocation engine | LOCKED, owner-authored contract |
| D-071 | Offline/Online Sync Architecture Contract (Phase 2): Online=existing single-tenant deployment for now (Sync Target ≠ Tenant Model); UUID scoped to distributed entities only, layered on local auto-increment ids; offline numbering uses server-leased ranges with a Draft/Pending state (never a provisional accounting number); event/change-based sync from day one (Outbox→Batch→Validation→Accepted/Rejected/Conflict→Inbox); server rejection is a first-class visible state, never a silent rollback; device identity via Pairing/Activation, independent of but connectable to Licensing; locked rollout order 2.1 Sync Contract→2.2 Device Pairing→2.3 UUID/Change Tracking→2.4 Outbox/Inbox→2.5 Customer/Product pilot→2.6 Conflict Resolution Workflow→2.7 Document/Invoice→2.8 Payment/Transaction | LOCKED, owner-authored contract |
| D-072 | Conflict Resolution Workflow (Phase 2 stage 2.6, redefined by the owner from "Conflict Handling" — detection already exists from 2.5): Detect(done)→Review queue→Resolve (KEEP_SERVER/KEEP_LOCAL/MANUAL_EDIT only, no generic merge)→a NEW operation_id through the SAME 2.5 push pipeline (never a direct mutation)→Converge; full conflict history preserved (local/server snapshots, versions, resolver, timestamp) with no sensitive data in snapshots; resolve is itself idempotent/retry-safe via the same operation_id mechanism | LOCKED, owner-authored contract |
| D-073 | Document/Invoice Sync Contract (Phase 2 stage 2.7): Document is the sync aggregate root, Invoice is dependent data; Sync state ≠ Accounting state (Draft syncs freely, Approved/Posted is strict and a server rejection is a visible conflict, never a silent rewrite); document numbers commit at finalization, never at Draft creation; D-072's resolution model applies pre-Posting only, post-Posting policy deferred to 2.8; hard DELETE removed from sync for financial documents (Void/Cancel/Reverse only); sync is dependency-aware (unaccepted parent → `Pending Dependency`, never a dropped record or failed batch); staged 2.7A Document Draft→2.7B Invoice Draft→2.7C Finalize/Numbering→2.7D Approved Document→2.7E hardening; Payment/Transaction/Posting/Cheque deferred to 2.8. **Scope addendum (2026-09-15): explicitly excludes Trade/Deal/Consignment/Open Exchange (see D-074) — 2.7 operates purely on the existing Document/Invoice model, no speculative Trade abstractions.** | LOCKED, owner-authored contract |
| D-074 | Trade/Consignment Architecture (future, documentation-only — no code yet): a `Trade`/`Deal` aggregate (`SALE`/`PURCHASE`/`CONSIGNMENT`/`OPEN_EXCHANGE`, CONSIGNMENT and OPEN_EXCHANGE kept as independent types, never collapsed) with multi-unit `Entries` (OUT/IN, native units, no forced up-front Rial conversion), a `Position` (running net-by-kind before settlement), separate `Valuations`, and a `Settlement` closing some/all of the open position — `Document` (what moved) vs. `Settlement` (how the account was squared) generalizes the D-070 pattern to a second future Settlement Engine source. Goods movement and financial settlement may post at different times (OPEN ≠ "nothing posted"). No `GenericTradeEntity`/`GenericPosition`/`GenericValuation`/`UniversalSettlement` may be built now "for later" — explicitly rejected as premature abstraction; real build happens in its own future phase against real requirements. Zero impact on Document/Invoice/SettlementService/PaymentService/SyncPilotService today; stage 2.7B proceeds independently and in parallel on the existing model | LOCKED AS FUTURE ARCHITECTURE ONLY, no implementation |
| D-075 | Offline Numbering & Reservation Contract (Phase 2 stage 2.7C, Invoice only): a Reserved Range is for official/finalized numbers only, never Drafts (`Draft -> Finalize -> consume next reserved number -> immutable`); a range is scoped to `(company_id, invoice_type, origin_device_id)` — Organization+Fiscal Year already collapse into `company_id` in this codebase's existing convention; range exhaustion blocks offline finalization (stays Draft, "Numbering unavailable," never a provisional number); ranges are server-authoritative via a locked atomic reservation, never a naive `MAX(number)+1`; reserved-but-unused numbers are never recycled in v1 (gaps are acceptable, reuse is not); reservation ≠ consumption — only Finalize (a new idempotent sync operation through the same pipeline) consumes a number and makes it immutable; the DB unique constraint (`invoices_number_invoice_type_company_id_unique`, already existing) remains the final collision guard, mirrored on the new `numbering_leases` table. Staged 2.7C-A Lease Contract→2.7C-B Reserve Range API→2.7C-C Local Range Consumption→2.7C-D Finalize Invoice→2.7C-E Exhaustion/Retry/Duplicate/Collision tests; 2.7D (Approved Document Sync) waits for 2.7C-E green; the 2.7B interim local-numbering workaround is transitional and is never the source of truth for a finalized number | LOCKED, owner-authored contract |

| D-076 | Approved Document/Invoice Sync Contract (Phase 2 stage 2.7D, Sales Invoice only): FINALIZED ≠ APPROVED/POSTED, never one operation; Approval cannot happen offline — server-authoritative only; server re-runs the EXISTING `InvoiceService::getChangeStatusValidation()`/`changeInvoiceStatus()` before posting, no new accounting engine; posted documents stay immutable through sync (UPDATE/DELETE rejected); three distinct outcomes — Technical Retry (operation_id idempotency), Business Rejection (REJECTED), Data/Accounting Conflict (CONFLICT via the existing SyncConflict flow) — never conflated; posting idempotent via the existing operation_id, a repeat APPROVE on an already-approved invoice is a safe no-op; new `device_installations.paired_by_user_id` (set once at pairing) gives a sync-triggered approval a real acting user to attribute the posting to. Item-level (`InvoiceItem`) sync remains explicitly out of scope | LOCKED, owner-authored contract |

| D-077 | Document/Invoice Sync Integration & Hardening Contract (Phase 2 stage 2.7E) — explicitly NOT a new feature: proves 2.7A–2.7D compose correctly as one chain (Customer→Invoice Draft→Dependency→Finalize+Number→Approve/Post→Retry/Conflict/Recovery→Converged state) with no duplicate posting, orphan document, lost operation, silent overwrite, or wrong numbering. Locked gate: partial batch failure yields independent per-operation outcomes; retry always preserves `operation_id`; crash-after-server-success is indistinguishable from a normal idempotent retry; Finalize/Approve have an atomic boundary; stale-version conflict is never confused with idempotent no-op (this caught and fixed the SAME ordering bug in `applyFinalize()` that D-076 had already fixed in `applyApprove()`); lifecycle enforcement verified across Draft/Finalized/Approved; multi-device convergence is the top-level invariant; exactly-once accounting posting verified as its own dedicated gate. A defect in sync infrastructure is fixed in place; an accounting-semantics question is split into its own future Decision, never resolved ad hoc here | LOCKED, owner-authored contract |

| D-078 | Distributed Financial Event & Payment Sync Contract (Phase 2 stage 2.8): Payment is a financial event, not an Invoice-like two-phase Draft — the existing atomic `Create Payment → Document → posting` behavior is preserved, sync manages it as one idempotent financial operation, no second accounting engine. Offline Payment creation is local-first with an explicit `LOCAL_PENDING_SYNC` state until server acceptance. A new `financial_event_uuid` identifies the economic event, distinct from `operation_id` (the sync attempt) — a retry preserves both. Payment creation and Allocation stay fully separate (Payment may have zero allocations). Posted Payments are immutable and never hard-deleted through any runtime — reversal/correction itself is deferred to its own future Decision. Cheque lifecycle stays fully independent of Payment Sync. Accounting posting is exactly-once per `financial_event_uuid`, surviving retries/timeouts/duplicates/crashes. Three lifecycles (Payment/Sync/Allocation) never share one status field. Customer→Subject materialization is out of scope (candidate D-079). Staged 2.8A Financial Event Identity→2.8B Offline Creation+Sync→2.8C Server Posting+Exactly-Once→2.8D Allocation Sync→2.8E Conflict/Retry/Crash/Reversal guards | LOCKED, owner-authored contract |

| D-079 | Offline Payment Creation & Sync Contract (Phase 2 stage 2.8B): Payment stays a one-step create=post operation, no `PaymentDraft`; offline creation is immediately usable in the UI (`POSTED_LOCALLY`/`sync=PENDING`/`server_acceptance=UNKNOWN`) without claiming server acceptance; server recognizes a replayed Payment via `(financial_event_uuid, operation_id)`, never duplicating it; `operation_id` for a Payment CREATE is stable across retries; Allocation Sync stays out of scope; both Credit Payment and Invoice-linked Payment must sync, Cheque-linked payments excluded; hard delete via Sync is rejected starting now (Web-side `deletePayment()` hard-delete remains a separate, unfixed pre-existing gap). Locked materialization answer from a pre-implementation audit: call the existing `PaymentService` method exactly once, then `forceFill` the resulting row's `uuid` to the client's `financial_event_uuid` (with `sync_version` explicitly reset to 1) — one posting, one identity fix-up, never a second creation | LOCKED, owner-authored contract |

| D-080 | Server Financial Authority & Reconciliation Gate (Phase 2 stage 2.8C, redefined from "Server Posting + Exactly-Once" after 2.8B's own gate already proved most of that): no new posting engine, no rebuilding 2.8B's create/retry/idempotency proof. Closes the gap 2.8B left open — same `financial_event_uuid` + matching payload → idempotent convergence (not conflict); same uuid + different payload → tamper/data conflict; different uuid + similar payload → treated as a genuinely separate event (proof of absence of fuzzy dedup); server business-validation failure on a locally-"posted" payment → deterministic REJECTED, never a silent local rollback; Received≠Validated≠Posted stay conceptually distinct while collapsing safely to REJECTED on any failure (one atomic transaction, no reachable partial state); server restart/replay re-verified; local+server converge to exactly one financial event. Flags a recurring "bare row from a legacy/system path breaks a new model-event hook" pattern (4th occurrence this session) as an Open Engineering Item, deliberately not fixed now | LOCKED, owner-authored contract |

| D-081 | Payment Allocation Sync Contract (Phase 2 stage 2.8D, batch-executed 2.8D-1..5): `PaymentAllocation` becomes its own independent Sync Entity with a canonical `uuid` (first extension of the UUID-scoped list since stage 2.3) — no new `SyncOperation`, reuses `CREATE` with dedicated apply logic; Sync never inserts into `payment_allocations` directly, only `Sync → dedicated apply → existing PaymentService::allocate()/allocatePayment() → existing lockForUpdate() → existing validation`; capacity failures (target already settled elsewhere) are Business Rejections through the existing validation, never a new conflict type or a re-implemented remaining-amount calculation; four independent lifecycles (Payment/Sync/Allocation/Accounting Posting) — an Allocation rejection never touches the Payment's own sync_status; each Allocation has its own independent `operation_id`; DELETE/reversal explicitly out of scope. Top invariant: `SUM(accepted allocations) <= target amount`, enforced entirely by existing `PaymentService` | LOCKED, owner-authored contract |

| D-082 | Distributed Payment & Allocation Integration Hardening (Phase 2 stage 2.8E, batch-executed 2.8E-1..5) — explicitly NOT a new feature, the final Integration Gate for the whole 2.8A–2.8D chain: Payment succeeds/Allocation fails leaves `Payment=synced, Allocation=rejected` with no blind rollback; chained retries/pulls/reconnects/duplicates never produce a second Payment or Allocation; D-080's same-identity/different-payload distinction re-verified at the chain level; conflicting cross-device allocations decided entirely by existing `PaymentService` validation; a crash between an accepted Payment and a pending Allocation resumes only the Allocation on reconnect, never recreating the Payment; pull/dependency ordering converges via D-081's existing mechanism; hard delete/invalid mutation stay rejected everywhere. Capstone: one end-to-end invariant test (Payment 100M, Allocations 60M+40M, under retries/duplicates/reconnect/pull/concurrent device) converging to exactly one posting and exact allocation totals. Explicit non-goals (reversal, Cheque, Trade, Customer→Subject redesign, new posting engine, new SyncOperation, generic conflict framework) trigger a stop-and-report, never silent scope expansion | LOCKED, owner-authored contract |

| D-083 | Phase 2 Sync Completion Gap Closure (post-Phase-2-Final-Audit): closes the audit's finding that 2.8B/2.8D were only proven in the receive/apply direction. Payment's existing `SyncsPaymentsToOutbox` local-enqueue trait gets its first direct test (no code change needed — it already worked). New `SyncsPaymentAllocationsToOutbox` trait gives `PaymentAllocation` its own local Outbox auto-enqueue, symmetric with Payment's, CREATE-only, Invoice-targets-only, no new `SyncOperation`, no bypass of `PaymentService::allocate()`/`allocatePayment()` | LOCKED, owner-authored contract |

| D-084 | Posted Financial Record Immutability at the Service Layer (post-Phase-2-Final-Audit): closes the audit's most significant finding — `PaymentService::deletePayment()`/`removeAllocation()` could still hard-delete a posted Payment/Allocation through the Web/domain path (Sync already rejected this). Both now unconditionally reject via a new `rejectPostedFinancialMutation()` guard; no new status/column; Reversal/Correction explicitly deferred to a future Decision. The cheque-linked branch of `deletePayment()` is explicitly excluded (independent Cheque lifecycle, D-070) — discovered empirically when an initial unconditional guard broke live, tested cheque-payment-deletion UI behavior. **Hardening addendum (2026-09-17, Batch A)**: the `Invoice::deleting()`/`cascadeOnDelete()` bypass is now closed too (`Invoice::booted()`'s hook rejects deletion of an invoice with a non-cheque Payment or any Allocation, same cheque carve-out). Remaining open bypass: the currently-dead-code `SettlementService::unsettleInstallment()` | LOCKED, owner-authored contract |

| D-085 | **LOCKED, IMPLEMENTED, VERIFIED.** Company Deletion Provenance Boundary. Owner chose Option A (provenance mechanism) over Option B (treat all copied history as immutable, which would break the ordinary "copy a fiscal year, delete a mistaken copy" workflow). Smallest design found by inspection: one new column (`documents.provenance`, `FinancialProvenance::NATIVE\|BASELINE`, NATIVE the safe default for every existing/legacy row and every ordinary creation path) set to BASELINE only by `FiscalYearService::_importDocuments()` -- the single choke point every copy/import path routes through. `PaymentAllocation` needs no column at all: it is never copied by any existing import path, so its mere existence is already a reliable native-activity signal. `Company::deleting()` rejects deletion when any approved NATIVE Document or any PaymentAllocation exists for the Company. `FiscalYearTransferService` (per-document transfer, a different feature) deliberately left untouched -- a transferred document safely defaults to NATIVE. No cascade/FK changed. **Final hardening (owner-required)**: two boundary tests proved (A) a copied Payment referencing copied Documents is correctly BASELINE and deletable, (B) editing a copied invoice correctly produces a brand-new NATIVE Document (never leaves the old one BASELINE) and immediately blocks deletion -- no bypass found. Found and fixed a real, unrelated bare-row bug along the way: `_importInvoices()`'s `saveQuietly()` silently left copied invoices with `uuid = NULL` (no DB default, unlike sync_version/sync_status), crashing on any later `update()`. 14 tests total (`CompanyDeletionFinancialGuardTest`, +10 net), full suite 1384/1384. **D-085: FINAL** | LOCKED, owner-authored contract |

| D-086 | **LOCKED/IMPLEMENTED for its confirmed scope; one item DECISION PENDING.** Approved Invoice Financial Immutability. `InvoiceService::updateInvoice()` now rejects any change to an Invoice's financial content (items/totals/customer/warehouse, and the approved-rebuild branch outright) once it has a real Payment or PaymentAllocation -- this path had zero guard at any layer before. Non-financial metadata (title/description/date) stays editable; an Approved invoice with no payment yet is completely unaffected (existing COG-correction workflows untouched). **Deliberately NOT implemented**: guarding `unapproveInvoice()`/`changeInvoiceStatus('unapproved')` itself -- doing so broke `GroupActionService`'s existing, trusted internal mechanism for cycling a downstream invoice's status to keep COG chains consistent when an earlier Buy invoice changes. Open question, now resolved by D-087. 7 tests (`ApprovedInvoiceFinancialImmutabilityTest`), full suite 1391/1391 | LOCKED, fully closed by D-087 |

| D-087 | **LOCKED, IMPLEMENTED, VERIFIED.** Explicit Separation of User-Facing Unapprove From Internal COG Status Cycling. Owner explicitly rejected a boolean bypass flag (`$internal`/`$skipGuard`) on `unapproveInvoice()` as the fix for D-086's open item. Two structurally distinct entry points instead: `unapproveInvoice()` (private, the only path `changeInvoiceStatus('unapproved')`/`InvoiceController::changeStatus()` can reach) now enforces the D-086 guard unconditionally; a new public `recycleInvoiceStatusForDependencyRecalculation()` is reserved for `GroupActionService`'s existing, trusted internal mechanism, provably non-financial (never touches amount/vat/customer/items/Payment/PaymentAllocation, verified even against an already-Paid invoice) even though it does delete/recreate the Document row and recompute COG -- an intrinsic, pre-existing part of that mechanism, not new. A reflection-based test proves no bypass flag exists anywhere. 6 tests (`InvoiceUnapproveBoundaryTest`), full suite 1397/1397. D-086 is now fully closed, no open items remain | LOCKED, owner-authored contract |

| D-088 | **LOCKED, IMPLEMENTED, VERIFIED.** Approved Invoice Re-Approval Is Service-Layer Guarded. Found during Phase 1's final boundary audit: `changeInvoiceStatus('approved')` had no guard of its own, relying entirely on its two callers' (controller, `SyncPilotService::applyApprove()`) own upstream checks. New private `userFacingApproveInvoice()` wrapper closes this symmetrically with the same already-locked D-086 invariant; `approveInvoice()` itself stays unguarded for its two legitimate direct callers (`recycleInvoiceStatusForDependencyRecalculation()`, `voidInvoice()`). 1 test added, full suite 1398/1398 | LOCKED, self-authored mechanical extension of D-086, owner-confirmed after the fact |

| D-089 | **LOCKED, IMPLEMENTED, VERIFIED.** Approved Invoice Rejection Is Service-Layer Guarded. Found during Phase 5's Cross-Module Financial Integrity audit: `changeInvoiceStatus('rejected')` was a bare status update with no guard of its own -- the one status-transition arm D-086/D-087/D-088 never covered. New private `userFacingRejectInvoice()` wrapper closes this symmetrically with the same already-locked D-086 invariant. 2 tests added, full suite 1403/1403. **Approved Invoice Mutation Boundary (D-086+D-087+D-088+D-089) is now CLOSED** on every confirmed front (edit/update/rebuild, unapprove, re-approve, reject, delete) -- exhaustive caller enumeration found no further gap | LOCKED, self-authored mechanical extension of D-086, same pattern as D-088 |

Full reasoning/sourcing for D-048–D-089 lives in `Dakhl_DECISION_LOG_2026-09-12-r4.md`; this table is a compact index only.

---

# 27. Cloud Audit Baseline

The 2026-09-12 Read-Only Cloud repository audit is now part of the project baseline. It must be referenced when planning P0/P1 implementation. The audit was read-only and produced no code changes.

Key verified findings:
- posted financial data is currently destructible/editable in existing paths
- company deletion can cascade into financial history
- float arithmetic is systemic
- cheque lifecycle/payment semantics need normalization
- no offline sync scaffolding exists yet
- `TaxSlab` is payroll-specific
- AR ageing is partially reusable from CRM
- company-specific roles remain unimplemented

Recommended immediate implementation gate:
1. protect financial history
2. enforce posted immutability
3. diagnose/fix desktop Login-500
4. establish decimal-safe money handling
5. then move into Payment Allocation and Reconciliation

---

# 28. Canonical Working Rule

> **Do not build more disconnected features. Build a coherent system in which the user states what happened, Dakhl understands the financial/tax implications, validates them, preserves evidence, and keeps the same truth synchronized across offline and online environments.**



# 29. Technical Decisions Baseline — 2026-09-12
## Status: LOCKED DIRECTION / IMPLEMENTATION TO FOLLOW IN CHECKPOINTED PHASES

This section converts the technical-decision research into project-level decisions. These are architecture directions, not permission to implement them immediately. Implementation must follow the priority order and Cloud/OpenCode checkpoint rules.

## 29.1 Offline/Online authority

**Superseded/sharpened by D-071 (LOCKED, 2026-09-15) — this subsection now restates that contract; `Dakhl_DECISION_LOG_2026-09-12-r4.md` is authoritative for full reasoning.**

**Decision:** Dakhl uses a local-first experience with server-authoritative reconciliation after sync.

Target flow:
`Local Business Operation -> Local Commit -> Outbox -> Sync API -> Server Re-validation + Idempotency -> Canonical Online Commit -> Ack`

Rules (D-071-sharpened):
- [ ] Every local operation gets a globally unique operation ID (envelope field, part of the Sync Contract — §2.1 in the locked implementation order).
- [ ] Local state distinguishes business/financial status from sync status (`synced`, `pending`, `SYNC_CONFLICT`, `SERVER_REJECTED` — D-071 decision 5: a rejection is a first-class visible state with `server_reason`/`local_version`/`server_version`, never a silent rollback).
- [ ] Server is authoritative for cross-device truth and externally authoritative states after reconciliation. "Server" in Phase 2 means the existing single-tenant deployment, not the future Organization/Tenant model (D-071 decision 1) — `Sync Target ≠ Tenant Model`.
- [ ] Tax-system acceptance is never claimed before external acknowledgement.
- [ ] Posted financial history remains immutable during conflict resolution.
- [ ] Canonical identity uses UUID **scoped to distributed/cross-device entities only** (`Customer`, `Product`, `Document`, `Invoice`, `Payment`, `Transaction`, `Subject` to start — D-071 decision 2), layered on top of each runtime's own local auto-increment `id`, never replacing it.
- [ ] Offline numbering uses **server-leased reserved ranges per device**; if a range is exhausted offline, the next document becomes `Draft`/`Pending Number` — **there is no provisional accounting document number** that could later be reassigned/reordered once real numbering is available (D-071 decision 3 explicitly rejects the earlier "provisional local references" framing this bullet used to have).
- [ ] Sync is event/change-based (Outbox → Sync Batch → Server Validation → Accepted/Rejected/Conflict → Inbox), never a full-database comparison (D-071 decision 7) — this generic envelope must exist even for the first low-risk pilot domain (Customer/Product), not be retrofitted later for Transaction/Payment.
- [ ] Device/company identity uses Pairing/Activation (`installation_uuid`/`device_key` → company binding → sync credential), kept as an independent domain from Licensing/D-048 even though the two interlock (D-071 decision 6).

## 29.2 Commercial / subscription model
**Decision:** subscriptions belong to the Organization/Tenant, not to a user or device.

Target hierarchy:
`Organization -> Companies -> Users/Seats -> Subscriptions -> Entitlements -> Licenses/Activations`

Rules:
- [ ] Core Accounting is the base product.
- [ ] Modules can be independently purchased where commercially justified.
- [ ] Industry bundles can package modules.
- [ ] Monthly and yearly subscriptions are supported; yearly is the preferred commercial plan.
- [ ] Mid-cycle add-on changes use a configurable proration policy.
- [ ] Downgrades normally take effect at renewal to avoid destructive mid-cycle behavior.
- [ ] Devices are primarily activation limits, not the default billing unit.
- [ ] Billing provider remains replaceable and must not define domain architecture.

## 29.3 License / Serial model
**Decision:** the entitlement is the authorization authority; the serial/license key is only an identifier/activation credential.

Target:
`Subscription -> Entitlement -> Signed License -> Activation -> Device`

Rules:
- [ ] Prefer Ed25519-signed license/entitlement payloads.
- [ ] Client contains only the public verification key.
- [ ] Offline clients verify cached signed entitlements locally.
- [ ] Default desktop offline grace policy: 14 days, configurable by plan.
- [ ] Expired modules never delete customer data.
- [ ] Expired modules may become read-only/limited until renewal.
- [ ] Device deactivation/transfer is supported.
- [ ] Server revoke/renewal applies on reconnect.

## 29.4 Initial Cloud / tenant architecture
**Decision:** start with a shared Laravel application and shared MariaDB/MySQL cluster with strict tenant isolation; do not introduce database-per-customer initially.

Target:
`Organization/Tenant -> Company -> Financial/Operational Records`

Rules:
- [ ] Organization/Tenant becomes the commercial/security boundary above Company.
- [ ] Preserve current Company/fiscal-year behavior during initial implementation.
- [ ] Every tenant-owned record must have a reliable tenant boundary directly or through mandatory relations.
- [ ] Isolation is enforced in middleware, services/query scopes, authorization and tests.
- [ ] Evidence/file storage is tenant-scoped.
- [ ] Dedicated database deployment remains a future enterprise/self-hosted option, not the initial default.

## 29.5 Tax governance
**Decision:** build a versioned Tax Rule Pack / Governance system, not a hard-coded tax calculator.

Each rule should support at least:
- jurisdiction
- tax type
- taxpayer/activity scope
- effective-from / effective-to
- priority
- calculation method
- exemptions/thresholds
- legal/source reference
- publication date
- implementation version
- approval status
- regression tests

Rules should be distributable as versioned/signed packs and updateable without rewriting the accounting core.

`TaxSlab` remains payroll-specific and is not the general Tax Rule Engine.

## 29.6 Industry extension architecture
**Decision:** use internal modular extensions first; consider separate packages only later when justified.

Target:
`Core Domain -> Industry Profile -> Industry Capabilities / Rules / Widgets / Providers`

Industry Profiles may control:
- dashboard widgets
- calculators
- vocabulary/defaults
- product attributes
- tax-classification defaults
- provider mappings
- pricing intelligence
- recommended bundles

Industry profiles must never fork the accounting engine and must not silently grant paid entitlements.

## 29.7 Market-data architecture
**Decision:** Provider/Adapter abstraction + normalized `MarketQuote`, with both Managed Provider and Bring Your Own API.

Rules:
- [ ] Web uses server-side provider adapters by default.
- [ ] Desktop direct provider access is allowed only when the provider explicitly supports it safely.
- [ ] API credentials are encrypted, tenant-scoped, masked and excluded from logs.
- [ ] Connection testing and instrument/symbol mapping are first-class.
- [ ] Primary/fallback provider support.
- [ ] Cached data includes freshness and provenance.
- [ ] Market data never changes posted historical financial records.
- [ ] Material market-price inputs to a transaction store a timestamped snapshot/provenance.

## 29.8 AI privacy and execution
**Decision:** introduce an AI Gateway / Policy Layer with Local AI, Dakhl-managed AI and Bring Your Own AI API as possible providers.

Default privacy rule:
> Do not send the full company database to an AI provider.

Rules:
- [ ] Fetch only data required for the task.
- [ ] Minimize/redact sensitive fields where possible.
- [ ] Prefer structured tool calls over giant free-form prompts.
- [ ] Consequential financial actions require appropriate user confirmation.
- [ ] AI uses the same permissions as the human user.
- [ ] Recommendations expose reason/evidence/confidence where applicable.
- [ ] Executed AI actions have audit trails.
- [ ] Provider/retention policy is visible and configurable at organization level.
- [ ] Local AI is optional and not a hard requirement for the first release.

## 29.9 Data portability
**Decision:** Dakhl provides a first-class versioned machine-readable full-data package in addition to human report exports.

Target export package contains, as applicable:
- structured company/accounting data
- attachments/evidence
- audit-history references
- source identifiers
- schema/version metadata
- checksums

Import flow:
`Source -> Mapping -> Validation -> Preview -> Commit`

Rules:
- [ ] Never write external import directly into posted financial tables.
- [ ] Preserve source-system identifiers and metadata.
- [ ] Support human exports separately from full machine migration packages.

## 29.10 Period Close
**Decision:** financial periods require explicit close semantics before the system can claim mature accounting integrity.

Target:
`Open -> Review -> Reconciled -> Closed -> Locked`

Rules:
- [ ] Closed/locked history is not edited/deleted directly.
- [ ] Corrections use reversal/adjustment entries.
- [ ] Close status is auditable.
- [ ] Reopening a closed period requires controlled authorization and audit trail.

## 29.11 Decision status
These technical decisions are now the recommended project baseline. Any later change must include:
1. reason/evidence,
2. affected modules,
3. migration/compatibility impact,
4. explicit decision-log update,
5. no silent architectural drift.

---

# 30. Roadmap Reprioritization — 2026-09-14

## Status: LOCKED, supersedes the original ordering in §23/§24

This reordering is not a change of strategy — every locked direction in §26/§29 stands. It reflects two things that happened after r7 was written: (1) all five P0 safety items plus the rebrand are now code-complete (§23 Phase 0), freeing up the next slot; (2) a systematic competitor sweep (11 Iranian accounting/ERP products directly researched, feature-by-feature, not just marketing copy) produced concrete evidence that changes what should happen next and in what order.

**Why Offline/Online architecture (Phase 2) moved ahead of UX polish and tax depth:** D-068 is the load-bearing finding — of 11 competitors researched (Holoo, Sepidar/System Group, Mahak, Hesabfa, Chalak Hesab, Moein, Qiyas, Abarestan, Chortke, Borhan System Pasargad, Kasbe), **every single one** that claims "desktop + cloud" actually ships two separately-coded products under one brand, not a shared engine. This is Dakhl's most validated, hardest-to-copy differentiator — but only if it is actually built as one engine. The risk of leaving it at position #10 (its original slot) is that Phase 1/3/5 work gets built against today's plain MySQL/SQLite split and has to be retrofitted later, which is exactly the trap every competitor fell into. Building it earlier costs more up front but avoids inheriting the industry's own failure mode.

**Why the WooCommerce plugin (D-049) was re-scored from "differentiator" to "baseline expectation":** the original 5-competitor research pass found only Hesabfa with a real WooCommerce plugin. The follow-up sweep found four more (Moein, Helo, Chortke, Chahaar Bagh) — WordPress-plugin support is explicitly listed as a buyer-selection criterion in at least one independent market blog. This doesn't change the decision (D-049 stays locked), it changes the framing: shipping it is table stakes, not a wedge.

**Why UX/onboarding investment (Phase 3) is prioritized above deeper tax/reporting features despite being "just UI":** the single most consistently repeated user complaint across the researched competitors (Holoo, Sepidar) is support/onboarding quality, not missing features — and independent research found no competitor publicly describing any deliberate in-app onboarding methodology, meaning this is genuinely uncontested ground, not a feature race.

**New candidate item added, not yet scoped (D-062):** Abarestan's per-invoice payment-link + e-wallet + auto-reconciliation (via IDPay) has no equivalent in any of our other 10 tracked competitors or in Dakhl's own roadmap. It is placed in Phase 5 pending its own design pass — likely intersecting Phase 1's Payment Allocation work and Phase 5's API platform, not a standalone module.

**What did not change:** the Protected Accounting Core (§4), the Non-Negotiable Product Principles (§2), and every LOCKED (not LOCKED DIRECTION) decision in §26/§29 are unaffected — this reprioritization is about sequencing and evidence, not about revisiting settled architecture.
