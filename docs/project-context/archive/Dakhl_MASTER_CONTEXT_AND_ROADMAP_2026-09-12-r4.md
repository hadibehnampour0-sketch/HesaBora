# دخل / Dakhl — Master Context + Roadmap
## Version: 2026-09-12-r4
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

### 5.1 Desktop authentication
- [ ] Fix invalid-login 500
- [ ] Friendly wrong-username handling
- [ ] Friendly wrong-password handling
- [ ] Correct-credential success path
- [ ] Fresh-install regression
- [ ] Production error handling verified

### 5.2 Cheque/payment integrity
- [ ] Canonical cheque lifecycle semantics
- [ ] Prevent duplicate posting at registration/clear
- [ ] Bounce fully restores invoice/customer state
- [ ] Revisit guarantee-cheque execute semantics
- [ ] Prevent false cash/payment events
- [ ] Ensure corrections do not create duplicate financial movements

### 5.3 Money/posting hardening
- [ ] Remove unsafe float-dependent financial calculations where applicable
- [ ] Exact decimal-safe arithmetic at financial boundaries
- [ ] Preserve `SUM(transactions.value) = 0` approval invariant
- [ ] Strengthen posted-document immutability
- [ ] Review destructive cascade-delete behavior for financial history

### 5.4 Production security
- [ ] Debugbar development-only
- [ ] Debug state protected in production
- [ ] Sensitive token/activity-log exposure reviewed

---

# 6. P1 — PAYMENT ALLOCATION + BANK RECONCILIATION
## Status: HIGHEST-VALUE ARCHITECTURAL UPGRADE

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

- [ ] AR ageing
- [ ] AP ageing
- [ ] customer statement drill-down
- [ ] supplier statement
- [ ] overdue worklists

---

# 7. P2 — CHEQUE SETTLEMENT DOMAIN
## Status: PRODUCT DIFFERENTIATOR

## 7.1 CheckSettlement

Target:

`Cheque -> CheckSettlement -> Payment`

Core fields:

- amount
- settled_at
- user_id
- notes

Invariant:

`SUM(CheckSettlement.amount) <= Cheque.amount`

Capabilities:

- [ ] real settlement model/migration
- [ ] service/domain rules
- [ ] payment linkage
- [ ] fully-settled state
- [ ] audit history
- [ ] over-settlement guard

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

1. Desktop invalid-login 500 + release stability
2. Cheque/payment accounting integrity
3. Payment Allocation
4. Bank Reconciliation
5. AR/AP ageing + drill-down
6. CheckSettlement / guarantee-cheque correctness
7. Offline/Online architecture validation and sync foundation
8. Zero-Accounting-Knowledge UX foundation
9. Tax Profile + Rule Engine + Moadian/VAT compliance foundation
10. Evidence + Tax Readiness + reconciliation layer
11. Accounting Dimensions + Payment Terms/Installments + Recurring
12. API audit + API v1
13. Industry Intelligence / Market Data provider abstraction
14. Financial Intelligence / Copilot / AI actions
15. Multi-currency / Fixed Assets / deeper Inventory
16. Workflow/ecosystem expansion

Note: Offline/Online architectural decisions are not a late feature. They must inform implementation of every item from this point forward, even if visible sync UI is delivered later.

---

# 24. Open Decisions / Open Work

- Invalid-login 500: OPEN
- Cheque Settlement semantics: OPEN
- Cheque bounce/revert: OPEN
- Multi-invoice payment allocation: OPEN
- Bank reconciliation: OPEN
- Offline/Online synchronization architecture audit: OPEN
- API endpoint audit: OPEN
- API v1: OPEN
- Tax Compliance foundation: OPEN
- Evidence / Tax Readiness: OPEN
- Zero-accounting-knowledge UX: OPEN
- Industry Profile / Market Data platform: OPEN
- Managed Provider + BYO API: OPEN
- Financial/TAX Copilot: OPEN
- Company-specific roles: OPEN
- Workflow approvals: OPEN

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

---

# 27. Canonical Working Rule

> **Do not build more disconnected features. Build a coherent system in which the user states what happened, Dakhl understands the financial/tax implications, validates them, preserves evidence, and keeps the same truth synchronized across offline and online environments.**

