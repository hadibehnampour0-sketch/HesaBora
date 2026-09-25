# دخل / Dakhl — Master Context + Roadmap
## Version: 2026-09-20-r14
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

### 5.0 Immediate financial data-safety containment
**Status: IMPLEMENTED / VERIFIED through D-083/D-084/D-085. See §25 for the current authoritative engineering checkpoint.**
- [x] Service-layer immutability guards for Payment / PaymentAllocation
- [x] Invoice deletion bypass hardened
- [x] Provenance-aware Company deletion boundary implemented
- [x] Regression coverage and full-suite verification
- [ ] Approved Invoice edit/unapprove/rebuild boundary — next hardening item
- [ ] Reversal/Correction — future architecture/workstream

### 5.1 Desktop authentication
- [ ] Fix invalid-login 500
- [ ] Friendly wrong-username handling
- [ ] Friendly wrong-password handling
- [ ] Correct-credential success path
- [ ] Fresh-install regression
- [ ] Production error handling verified

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
## Status: PARTIALLY IMPLEMENTED — Payment Allocation is now implemented; Bank Reconciliation remains future work

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

# 17. P12 — FINANCIAL INTELLIGENCE / HESABORA COPILOT
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

1. **Immediate financial data-loss containment** — block destructive deletion of companies/posted financial history
2. **Posted financial immutability** — approved documents/transactions cannot be edited or deleted except through controlled correction flows
3. **Desktop invalid-login 500 + release stability** — reproduce from packaged EXE, confirm root cause, then fix
4. **Money/decimal safety** — establish a single safe arithmetic strategy before expanding financial calculations
5. **Cheque/payment accounting integrity** — registration/clear/bounce/return semantics and duplicate business-event prevention
6. **Payment Allocation**
7. **Bank Reconciliation**
8. **AR/AP ageing + drill-down** (reuse existing CRM ageing calculation)
9. **CheckSettlement / guarantee-cheque correctness**
10. **Offline/Online architecture validation + sync foundation**
11. **Zero-Accounting-Knowledge UX foundation**
12. **Tax Profile + Rule Engine + Moadian/VAT compliance foundation**
13. **Evidence + Tax Readiness + reconciliation layer**
14. **Accounting Dimensions + Payment Terms/Installments + Recurring**
15. **API audit + API v1**
16. **Industry Intelligence / Market Data provider abstraction**
17. **Modular Licensing + Subscription + Entitlement + Activation**
18. **Financial Intelligence / Copilot / AI actions**
19. **Multi-currency / Fixed Assets / deeper Inventory**
20. **Workflow/ecosystem expansion**

Note: Offline/Online architectural decisions are not a late feature. They must inform implementation of every item from this point forward, even if visible sync UI is delivered later.

---

# 24. Open Decisions / Open Work

### Newly confirmed / elevated from Cloud repository audit
- Company financial-data deletion guard: OPEN / P0
- Posted-document immutability enforcement: OPEN / P0
- Systemic decimal/float remediation: OPEN / P0
- Desktop DB boot/runtime root-cause verification for Login-500: OPEN / P0

### Existing open work
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
- General Tax Rule Engine vs payroll `TaxSlab` separation: OPEN (direction confirmed: do not reuse `TaxSlab` as canonical tax engine)
- Cascade-delete policy for companies with financial history: OPEN (P0 containment required)
- Modular Licensing / Subscription / Entitlement architecture: OPEN
- Module-specific serial/license activation: OPEN
- Offline license validation / grace policy: OPEN
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


# 25. Current Engineering Checkpoint — 2026-09-17

## Status: VERIFIED — POST-PHASE-2 FINANCIAL HARDENING

This section is the current engineering override for older baseline statements in this roadmap. Where an older section says a capability is "not implemented" or "greenfield", the later verified checkpoint below takes precedence.

### 25.1 Phase 2 completion

Phase 2 Offline/Online Sync is complete with open debt closed for the previously partial Payment and PaymentAllocation stages.

Verified sequence:
- 2.1 Sync Contract — VERIFIED
- 2.2 Device Pairing — VERIFIED
- 2.3 UUID + Change Tracking — VERIFIED
- 2.4 Outbox / Inbox — VERIFIED
- 2.5 Customer/Product Sync Pilot — VERIFIED
- 2.6 Conflict Handling — VERIFIED
- 2.7A Document Draft Sync — VERIFIED
- 2.7B Invoice Draft Sync — VERIFIED
- 2.7C Offline Numbering & Reservation — VERIFIED
- 2.7D Approved Sales Invoice Sync — VERIFIED
- 2.7E Integration & Hardening — VERIFIED
- 2.8A Payment Financial Event Identity — VERIFIED
- 2.8B Offline Payment Creation + Sync — VERIFIED
- 2.8C Server Financial Authority & Reconciliation Gate — VERIFIED
- 2.8D Payment Allocation Sync — VERIFIED
- 2.8E Financial Conflict/Retry Hardening — VERIFIED

### 25.2 Financial hardening decisions

The following are now locked and implemented:

- **D-070** — Cheque/payment boundary preserved.
- **D-071** — Offline/Online Sync architecture.
- **D-072** — Conflict Resolution Workflow.
- **D-073** — Document/Invoice Sync Contract.
- **D-074** — Trade/Consignment is future architecture only.
- **D-075** — Offline Numbering & Reservation.
- **D-076** — Approved Sales Invoice Sync.
- **D-077** — 2.7E integration/hardening gate.
- **D-078** — Payment financial-event identity and 2.8 scope.
- **D-079** — Offline Payment Creation + Sync.
- **D-080** — Server Financial Authority & Reconciliation.
- **D-081** — Payment Allocation Sync.
- **D-082** — 2.8E financial conflict/retry hardening.
- **D-083** — Local Payment/PaymentAllocation outbox completion.
- **D-084** — Posted financial record immutability at service layer.
- **D-085** — Provenance-aware Company deletion boundary.
- **D-086** — User-facing Invoice unapprove is service-guarded when financial settlement exists; the guard has no bypass flag/parameter and remains active on the public status-change path.
- **D-087** — Internal GroupAction COG dependency recalculation uses a separate explicit public entry point (`recycleInvoiceStatusForDependencyRecalculation`) rather than bypassing the guarded status-change method via flags/caller checks.

### 25.3 D-085 current rule

Company deletion is provenance-aware.

Financial records are treated as:

- `BASELINE` — intentionally copied/imported baseline history.
- `NATIVE` — local/native financial activity.
- `UNKNOWN/LEGACY` — any existing financial record without reliable provenance.

Deletion rule:

- Company with no financially effective records: **DELETE allowed**.
- Company with only copied/imported `BASELINE` financial history: **DELETE allowed**, subject to the implemented guard.
- Company with any `NATIVE` financial activity: **DELETE rejected**.
- Company with any `UNKNOWN/LEGACY` financial provenance: **DELETE rejected**.

Current implementation uses `documents.provenance` with `NATIVE` as the safe default and marks the single financial-document import/copy choke point as `BASELINE`. `PaymentAllocation` is not copied by the current fiscal-year import path, so its presence is treated as native financial activity for Company deletion.

The existing Company deletion guard remains a destructive-data safety boundary; database cascades must not create an alternate path that defeats it.

### 25.4 Verification state

Latest full-suite checkpoint:

**1397 passed / 0 failed / 0 skipped**

D-085 added 8 tests to the previous 1374-test baseline.

D-086/D-087 are verified in the current checkpoint; D-087 has 6 dedicated tests, the combined targeted regression for the affected financial/GroupAction/COG area is 101/101, and the full suite is 1397/1397.

The latest D-085 implementation was verified without introducing:
- a new Payment lifecycle state,
- a new Allocation lifecycle state,
- Reversal/Correction behavior,
- Git commits.

### 25.5 Current open engineering debt

The D-086/D-087 unapprove boundary is now closed and verified. The broader approved-invoice mutation surface is **not** considered closed unless its remaining update/edit paths are separately verified and locked. In particular, any future work touching `InvoiceController::update()` / approved-edit rebuild behavior must remain a separate checkpoint and must not weaken the D-086 guard.

Other open debt:
- `SettlementService::unsettleInstallment()` appears to be dead code with zero current callers.
- Bare-row patterns remain systemic and are intentionally not globally refactored.
- Two generations of Sync ingestion remain an architectural debt; financial Sync paths are hardened and tested.
- Reversal/Correction remains future work.
- Cheque Sync, Transaction Sync, Bank Reconciliation Sync remain future implementation work unless/when separately locked.
- Trade/Consignment remains future architecture only.
- Git baseline/configuration/real commits remain a separate explicit batch.

### 25.6 Execution model

Current autonomous execution rule:

**Locked business contract → larger implementation batch → targeted tests → full suite → regression audit → durable documentation → next independently locked/authorized batch.**

Routine implementation details do not require owner approval.

A genuinely new business-policy decision must stop only the affected workstream; unrelated safe work may continue.


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

### Recent financial-hardening decision additions

| ID | Decision | Status |
|---|---|---|
| D-070 | Cheque/payment boundary preserved | LOCKED / IMPLEMENTED / VERIFIED |
| D-071 | Offline/Online Sync architecture | LOCKED / IMPLEMENTED / VERIFIED |
| D-072 | Conflict Resolution Workflow | LOCKED / IMPLEMENTED / VERIFIED |
| D-073 | Document/Invoice Sync Contract | LOCKED / IMPLEMENTED / VERIFIED |
| D-074 | Trade/Consignment remains future architecture only | LOCKED |
| D-075 | Offline Numbering & Reservation | LOCKED / IMPLEMENTED / VERIFIED |
| D-076 | Approved Sales Invoice Sync | LOCKED / IMPLEMENTED / VERIFIED |
| D-077 | 2.7E integration/hardening gate | LOCKED / IMPLEMENTED / VERIFIED |
| D-078 | Payment financial-event identity and 2.8 scope | LOCKED / IMPLEMENTED / VERIFIED |
| D-079 | Offline Payment Creation + Sync | LOCKED / IMPLEMENTED / VERIFIED |
| D-080 | Server Financial Authority & Reconciliation | LOCKED / IMPLEMENTED / VERIFIED |
| D-081 | Payment Allocation Sync | LOCKED / IMPLEMENTED / VERIFIED |
| D-082 | 2.8E financial conflict/retry hardening | LOCKED / IMPLEMENTED / VERIFIED |
| D-083 | Local Payment/PaymentAllocation outbox completion | LOCKED / IMPLEMENTED / VERIFIED |
| D-084 | Posted financial record immutability at service layer | LOCKED / IMPLEMENTED / VERIFIED |
| D-085 | Provenance-aware Company deletion boundary | LOCKED / IMPLEMENTED / VERIFIED |
| D-086 | User-facing Invoice unapprove is guarded when financial settlement exists; no flag/caller-check bypass | LOCKED / IMPLEMENTED / VERIFIED |
| D-087 | Internal GroupAction COG dependency recalculation uses a dedicated unguarded entry point, separate from the user-facing guarded status-change API | LOCKED / IMPLEMENTED / VERIFIED |

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
**Decision:** Dakhl uses a local-first experience with server-authoritative reconciliation after sync.

Target flow:
`Local Business Operation -> Local Commit -> Outbox -> Sync API -> Server Re-validation + Idempotency -> Canonical Online Commit -> Ack`

Rules:
- [ ] Every local operation gets a globally unique operation ID.
- [ ] Local state distinguishes business/financial status from sync status (`synced`, `pending`, `conflict`, `rejected`).
- [ ] Server is authoritative for cross-device truth and externally authoritative states after reconciliation.
- [ ] Tax-system acceptance is never claimed before external acknowledgement.
- [ ] Posted financial history remains immutable during conflict resolution.
- [ ] Canonical identity uses UUID; display document numbering follows dedicated numbering policy.
- [ ] Offline numbering uses reserved ranges when available; otherwise provisional local references are clearly marked and must not be represented as officially accepted electronic invoices before synchronization/submission.

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

# 30. Cloud Autonomous Multi-Session Engineering Charter — 2026-09-12
## Status: LOCKED OPERATING MODEL FOR THE 4-DAY AUTONOMOUS WINDOW

Dakhl development may proceed across multiple Cloud/agent sessions without requiring the owner to re-issue the full task after every context/token limit or reset.

The autonomous agent must operate under these rules:

### 30.1 Durable project memory
The following are the durable project context:
- Master Context + Roadmap
- Decision Log
- Canonical Context Index
- supporting research
- `CLOUD_AUTONOMOUS_STATE.md`
- `CLOUD_AUTONOMOUS_EXECUTION_LOG.md`
- Git history and current working tree

After every reset/session:
1. Read current canonical context.
2. Read `CLOUD_AUTONOMOUS_STATE.md` if present.
3. Read `CLOUD_AUTONOMOUS_EXECUTION_LOG.md` if present.
4. Run `git status` and inspect recent commits/diff.
5. Resume from the last safe GREEN checkpoint; do not redo completed work.

### 30.2 Autonomous decision authority
The agent may autonomously decide implementation details, test structure, migration mechanics, helper abstractions, small reversible refactors, and create small sub-phases when required.

The agent may NOT silently change:
- product vision
- locked accounting source of truth
- locked Offline/Online model
- locked tax/compliance direction
- locked licensing/entitlement model
- locked AI safety/privacy model
- locked industry/provider architecture
- protected core principles

If an implementation issue conflicts with a locked decision, document the conflict and stop only the affected decision/workstream; continue unrelated safe work.

### 30.3 Autonomous phase creation
The roadmap is a prioritized system of work, not a fixed numbered checklist.

The agent may create new small phases when a necessary dependency or architectural prerequisite is discovered, provided the phase is:
- necessary
- scoped
- testable
- reversible where practical
- consistent with locked decisions

Every new phase must be recorded in the execution log.

### 30.4 Continuous execution
After a phase becomes GREEN:
- run required regression tests
- review diff
- create a coherent local commit when appropriate
- update durable state/log
- automatically select the next highest-value safe phase
- continue without waiting for routine human confirmation

A blocked phase must not stop unrelated safe work.

### 30.5 Context/token limit handling
When approaching a context/token/session limit:
- do not start another large implementation
- finish or safely pause the current sub-step
- update `CLOUD_AUTONOMOUS_STATE.md`
- append the execution log
- commit safe GREEN work if appropriate
- leave the repository recoverable

The next session must continue from this durable state.

### 30.6 Safety stop conditions
Stop or quarantine only the affected workstream when discovering:
- actual or imminent financial data corruption
- unsafe/destructive migration
- irreversible data loss
- serious security vulnerability
- unresolved accounting ambiguity that can change financial truth
- contradiction with a locked architecture decision

Do not hide failures to maintain apparent progress.

### 30.7 Engineering quality rule
The objective is not maximum code volume. The objective is:
- safer accounting
- stronger data integrity
- better tests
- maintainable architecture
- progressive UX simplification
- readiness for Offline/Online, Tax, Licensing, Industry Intelligence and AI

Correctness has priority over speed.

### 30.8 Autonomous window
During the current multi-day autonomous window, the agent may continue through P0/P1 foundations and then into safe architectural/vertical-slice work across Offline/Online, UX, Tax, Licensing and Industry Intelligence as long as prerequisites are green and no safety boundary is crossed.

Full modules do not have to be completed within one session; durable checkpoints are the unit of progress.

---

# 31. Current Cloud Audit Baseline — Post-Read-Only Audit
## Status: VERIFIED / MUST DRIVE NEAR-TERM EXECUTION

The 2026-09-12 Cloud read-only audit confirmed:
- posted financial immutability is not currently enforced in code
- Company deletion can cascade into posted financial history
- float arithmetic is systemic in financial services
- cheque lifecycle has duplicate-payment/state risks
- PaymentAllocation and CheckSettlement are not yet first-class domains
- Bank Reconciliation is not implemented
- Offline/Online synchronization scaffolding is not implemented
- the packaged Desktop Login-500 root cause remains to be confirmed by runtime reproduction/log inspection
- `TaxSlab` is payroll-specific and must not be treated as the general Tax Rule Engine
- AR ageing logic already exists in CRM and should be reused/generalized where appropriate

These are implementation findings, not assumptions.

Near-term priority is therefore:
1. protect financial history from destructive deletion
2. enforce posted-record immutability
3. reproduce and fix Login-500
4. reduce financial float-safety risk at high-value boundaries
5. harden cheque/payment lifecycle
6. build Payment Allocation
7. build Bank Reconciliation
8. validate the Offline/Online vertical slice
9. then expand Tax / UX / Licensing / Industry / AI foundations

---


# 31.1 Current verified override — 2026-09-17

The older repository-audit statements in §31 described PaymentAllocation and Offline/Online sync as not implemented. Those statements are superseded by §25: Phase 2 and its financial sync stages are now implemented and verified.

Current latest verified state:
- Phase 2 Sync: COMPLETE WITH OPEN DEBT
- D-083: LOCKED / IMPLEMENTED / VERIFIED
- D-084: LOCKED / IMPLEMENTED / VERIFIED
- D-085: LOCKED / IMPLEMENTED / VERIFIED
- D-086: LOCKED / IMPLEMENTED / VERIFIED
- D-087: LOCKED / IMPLEMENTED / VERIFIED
- Full suite: 1397/1397 passed
- Git baseline: NOT EXECUTED; 0 real commits


---

# 32. Durable Execution Artifacts

Cloud/agents should maintain these repository files when executing autonomously:

- `docs/project-context/CLOUD_AUTONOMOUS_STATE.md`
- `docs/project-context/CLOUD_AUTONOMOUS_EXECUTION_LOG.md`
- `docs/project-context/CLOUD_AUTONOMOUS_FINAL_REPORT.md`

These are operational state/history files and must be append/update safe; they must never replace or override the Master Context or Decision Log.

---

# 33. Updated Canonical Working Rule

> **Do not build more disconnected features. Build one coherent financial system in which the user states what happened, Dakhl understands the accounting and tax implications, prevents data loss, preserves evidence, keeps history traceable, works offline and online, and continues safely across agent sessions.**


# 34. Current Verified Override — 2026-09-20
## Status: AUTHORITATIVE CURRENT PHASE BASELINE

This section supersedes older roadmap statements where they conflict with the verified current repository state and the decisions recorded during the Organization Authorization & Workflow Governance planning pass.

### 34.1 Financial Foundation closure
- Financial Foundation / Financial Integrity phase: CLOSED.
- Latest verified full suite: **1426/1426 GREEN**.
- D-090: CLOSED.
- Phase Exit Gate: all 22 items GREEN.
- Major verified hardening included invoice approve/unapprove idempotency, AncillaryCost idempotency, cheque/settlement boundaries, money rounding choke-point hardening, provenance/import safety, authorization, sync/idempotency and destructive-action protections.

### 34.2 Current execution phase
**Organization Authorization & Workflow Governance**

Goal:
Establish safe company-scoped authorization, membership/role governance, approval workflows, segregation-of-duties where justified, authorization parity across Web/API/Offline/Sync, and auditable workflow decisions without weakening the closed Financial Foundation.

### 34.3 Locked decision — legacy global-role migration
Existing users who currently hold global roles retain the same effective access across every Company they are already members of.

Migration rule:
- Materialize the existing global role assignment into equivalent company-scoped role assignments for every current `company_user` membership.
- No intentional permission loss on migration day.
- Migration is idempotent and must not create duplicate memberships or role assignments.
- New company memberships after the migration boundary do **not** implicitly inherit the legacy global role.
- Future role changes are company-scoped.
- Existing legacy/global role records may remain only as a controlled migration/compatibility concern; they must not silently bypass the company boundary after migration is established.

### 34.4 Locked decision — current tenant/security boundary
**Company is the tenant/security boundary for the current phase. No new Organization model is introduced now.**

Current model:
`User -> Company -> Membership/Role/Permission -> Data`

The previously documented Organization/Tenant concept remains a future architecture option only. It may be introduced later when a real product need requires grouping multiple Companies under one commercial/security owner.

Do not introduce speculative Organization tables, organization-level roles, organization-level permission inheritance, or organization membership in the current phase.

### 34.5 Verified Spatie implementation constraint
Repository/package inspection confirmed that Spatie Permission `teams` support is currently disabled and current roles are global. `company_user` is currently a bare membership pivot (`id`, `company_id`, `user_id`).

The current implementation phase may enable Spatie team scoping using the existing active-company context, but implementation must:
- preserve existing role definitions where package semantics allow,
- make role assignments company-scoped,
- use `company_id` as the team foreign key,
- bind Spatie team context to the existing active-company mechanisms (`DefaultCompany`, `SetApiCompany`, `AuthenticateDevice` where applicable),
- remain compatible with both SQLite test environments and MySQL/MariaDB production,
- provide an idempotent backfill and migration path,
- prove the behavior with full regression coverage.

### 34.6 Current phase exit requirement
The phase is not complete merely when company-scoped roles exist. The agent must also complete the broader Authorization & Workflow Governance audit, including:
- membership lifecycle
- permission matrix
- service-layer authorization
- approval workflows/state machines
- idempotent approvals and role mutations
- segregation-of-duties rules where justified
- API authorization parity
- Offline/Sync authorization parity
- audit/evidence of security-sensitive decisions
- schema/constraint consistency
- concurrency/atomicity checks
- legacy compatibility
- functional authorization/workflow UX
- adversarial re-audit
- complete regression/full-suite verification

### 34.7 Autonomous execution rule
This phase must be executed as one large autonomous engineering batch. Routine implementation details do not require owner confirmation. The agent should continue from migration through the rest of the phase without stopping at artificial checkpoints. It should stop only for a genuinely new business/architecture decision that cannot be derived from locked decisions and repository evidence.

### 34.8 Git baseline
- HEAD remains `12e3874a` unless repository work subsequently changes it.
- No real Git commits existed at the start of this phase; Git identity was unset.
- Agents must report actual Git state and never claim queued commit text is a real commit.

# 35. Current Verified Override — 2026-09-20 (Organization Authorization A–I)
## Status: IN-PROGRESS / CORE MIGRATION GREEN / FULL PHASE NOT YET CLOSED

This section supersedes prior current-phase status where it conflicts with the latest verified repository execution.

### 35.1 Current baseline
- Financial Foundation / Financial Integrity: **CLOSED**.
- Financial Foundation final suite: **1426/1426 GREEN**.
- Current phase: **Organization Authorization & Workflow Governance**.
- Organization Authorization Workstreams **A–I: COMPLETE / VERIFIED**.
- Latest full-suite result after A–I: **1429/1429 GREEN**.
- Current HEAD: `12e3874a`.
- Real Git commits: **0**.
- Git identity: still unset.
- Commit 51: queued/documented only; not a real commit.

### 35.2 Locked decision — Company is the tenant/security boundary
**Company remains the sole current tenant/security boundary.**

No Organization model/table/membership/role hierarchy was introduced in this phase.

Current authorization shape:
`User -> Company Membership -> Company-Scoped Role -> Permissions -> Company Data`

An Organization concept remains future architecture only and may be introduced later when a concrete product requirement justifies grouping multiple Companies under one commercial/security owner.

### 35.3 Locked decision — legacy global-role migration
For existing users who held global roles before company scoping:

- Preserve their effective access in every Company they are already a member of.
- Materialize each existing role assignment into equivalent company-scoped assignments for every current `company_user` membership.
- Migration/backfill must be idempotent and duplicate-safe.
- No intentional permission loss on migration day.
- New Companies joined after the migration boundary do **not** automatically inherit the user's previous global role.
- Future role assignment/change is company-scoped.

### 35.4 Verified Spatie company-scoped authorization implementation
The installed Spatie Permission package originally had `teams` disabled and roles were global.

Verified implementation state after A–I:
- Spatie `teams` is enabled.
- `company_id` is used as the team foreign key.
- Existing reusable role definitions remain shared/global where package semantics support it.
- User role assignments are company-scoped.
- Active Company is resolved through the existing company-context mechanism rather than introducing a parallel company context.
- Super-Admin remains an explicit cross-company/global path using the existing sentinel/global authorization mechanism and does not incorrectly depend on team-scoped role lookup.

### 35.5 Critical migration defect discovered and fixed
A real upgrade-path defect was discovered during the Spatie migration:

An initial migration design attempted to add a foreign key from `roles.company_id` to `companies.id`. On SQLite, the required table rebuild interacted with Spatie's existing cascading foreign keys from `model_has_roles` / `role_has_permissions` and could silently remove role/permission rows during a real upgrade.

This was not visible through normal fresh-install / `RefreshDatabase` coverage.

The implementation was corrected by removing that unnecessary FK and adding an upgrade-style regression test that exercises an existing database being migrated forward.

Important rule:
- Do not reintroduce `roles.company_id -> companies.id` merely for referential cleanliness.
- Future schema changes must distinguish fresh-install success from real upgrade-path safety.

### 35.6 Cross-company authorization defect discovered and fixed
`RoleController` was found to lack company scoping for custom-role access, allowing one Company to potentially view/edit/delete roles belonging to another Company.

This was fixed and regression-tested.

This finding establishes an explicit phase guardrail:
> A generic permission is insufficient if the target resource itself is not constrained to the active Company.

### 35.7 Migration compatibility tests
New regression coverage in `tests/Feature/CompanyScopedRoleMigrationTest.php` verifies, as applicable:
- multi-company user role materialization
- single-company user
- user without company membership
- Super-Admin sentinel behavior
- rerun/idempotency safety
- role isolation between Companies
- no implicit role inheritance for newly joined Companies

Only the existing `AuthLifecycleTest.php` required direct adaptation among the pre-existing affected tests reported by the agent.

### 35.8 Current phase status
Organization Authorization & Workflow Governance is **NOT CLOSED** yet.

Completed:
- A — baseline / architecture mapping
- B — role definitions vs assignments
- C — Spatie teams enablement
- D — active-company/team context
- E — existing global-role backfill
- F — migration safety / upgrade verification
- G — legacy compatibility for the migrated authorization model
- H — new-membership behavior
- I — role/membership lifecycle foundations already covered by the current pass

Remaining closure scope:
- J — permission matrix
- K — service-layer authorization final sweep
- L — workflow / approval foundation
- M — approval state-machine adversarial testing
- N — segregation of duties analysis/enforcement where justified
- O — API authorization parity
- P — Offline/Sync authorization parity
- Q — audit trail / security evidence
- R — database constraints / consistency
- S — concurrency / race conditions
- T — permission cache / team-context isolation
- U — complete cross-company adversarial attack
- V — functional authorization/workflow UX
- W — test-quality / test-teeth audit
- X — full regression
- Y — domain/product analyst pass
- Z — final adversarial release audit
- AA — roadmap/state/decision-log reconciliation

### 35.9 Phase exit rule
The phase must remain OPEN until the remaining J–AA closure work is completed and the full Phase Exit Gate is GREEN.

A higher test count alone is not sufficient evidence of closure.

### 35.10 Autonomous continuation rule
The remaining J–AA work must be executed as one large autonomous batch. Routine implementation details do not require owner confirmation.

The agent should:
`analyze -> reproduce -> implement -> targeted tests -> full regression -> adversarial re-audit -> document -> continue`

It should stop only for a genuinely new business/architecture decision that cannot be derived from the locked decisions and repository evidence.

# 36. Decision Register — Organization Authorization & Workflow Governance

| ID | Decision | Status |
|---|---|---|
| D-091 | Existing global user roles are materialized to all Companies the user is currently a member of; future role changes are company-scoped; new memberships do not inherit legacy roles automatically | LOCKED / IMPLEMENTED THROUGH A–I / VERIFIED |
| D-092 | Company is the current tenant/security boundary; no Organization model is introduced in this phase | LOCKED / IMPLEMENTED / VERIFIED |
| D-093 | Spatie Permission `teams` uses `company_id` as the team context; reusable role definitions remain shared where supported, while assignments are company-scoped | LOCKED / IMPLEMENTED / VERIFIED |
| D-094 | Active Spatie team context derives from the existing active-company context; no parallel company-context mechanism is introduced | LOCKED / IMPLEMENTED / VERIFIED |
| D-095 | Super-Admin remains an explicit cross-company/global authorization path and must not be accidentally constrained by the normal company-scoped role resolver | LOCKED / IMPLEMENTED / VERIFIED |
| D-096 | Real upgrade-path migration testing is mandatory for authorization schema changes; fresh-install tests alone are insufficient | LOCKED / IMPLEMENTED / VERIFIED |
| D-097 | Do not introduce a `roles.company_id -> companies.id` FK solely for referential cleanliness; the SQLite upgrade hazard was proven and the safe design omits it | LOCKED / IMPLEMENTED / VERIFIED |
| D-098 | Custom RoleController operations must be constrained to the active Company/resource ownership, not merely protected by a generic role-management permission | LOCKED / IMPLEMENTED / VERIFIED |

# 37. Current Verified Override — 2026-09-20 (Post A–I Authorization Hardening)
## Status: AUTHORITATIVE CURRENT EXECUTION BASELINE / PHASE STILL OPEN

This section is the latest verified override and supersedes older current-status statements in this file where they conflict with the latest repository execution evidence.

### 37.1 Latest verified state

- Financial Foundation / Financial Integrity: **CLOSED**.
- Financial Foundation exit gate: **22/22 GREEN**.
- Organization Authorization & Workflow Governance: **IN PROGRESS / NOT CLOSED**.
- Workstreams A–I: **COMPLETE / VERIFIED**. K, N, O, P, R, T also verified/substantively addressed this continuation (see §37.6).
- Latest full application suite: **1435/1435 GREEN**, including the enforced `company_user` uniqueness constraint (§37.5), on both SQLite and real MariaDB.
- Current recorded HEAD: `12e3874a` unless subsequent repository state legitimately changes it.
- Real Git commits: **0** at the recorded baseline.
- Git identity: still unset unless repository state legitimately changes it.
- Commits 51, 52, and 53 are queued/documented text in `CLOUD_AUTONOMOUS_STATE.md` only — **none of them are real Git commits**.

### 37.2 Latest MariaDB migration verification

The authorization migration path has now been exercised on a real **MariaDB 12.3** environment, including:

- fresh installation path,
- legacy schema -> Teams upgrade path,
- role/permission preservation checks,
- migration idempotency behavior,
- company-scoped role behavior after upgrade.

A MariaDB-specific index-length defect in `numbering_leases` was discovered during this verification and corrected.

This strengthens D-096: authorization schema changes must be proven through a real upgrade path, not only through fresh-install / `RefreshDatabase` execution.

### 37.3 Permission cache / team-context isolation — verified hardening

A real defect was reproduced in the wildcard-permission path caused by the existing `enable_wildcard_permission=true` behavior and a stale per-user wildcard permission index.

Observed failure mode:

`Company A permission resolution -> switch to Company B -> permission check still returns Company A result`

The direct role path (`hasRole()`) did not exhibit the same defect; the affected path was wildcard-backed permission resolution.

Fix now implemented in `App\Support\CompanyPermissionsTeamResolver`:

- every team-context resolution compares the newly resolved `company_id` with the last resolved value;
- when the ambient context changes, the wildcard permission index is invalidated;
- invalidation is therefore not dependent only on explicit `setPermissionsTeamId()` calls;
- stale ambient context paths are covered as well.

Related cleanup was also completed in the affected controllers: context restoration now explicitly clears the team context using `setPermissionsTeamId(null)` rather than restoring a captured potentially-stale value.

Regression coverage:
- `tests/Feature/CompanyAuthorizationIsolationTest.php`
- **5/5 passing** in the reported verification run.

Workstream T is therefore **partially/strongly verified for the discovered defect class**, but T remains open until the full J–AA adversarial pass confirms isolation across all authorization entry points, API context, retries, role mutation and process-state boundaries.

### 37.4 Role-assignment audit trail — verified hardening

Role assignment/removal was found to bypass the existing model-event-based activity visibility because the implementation performs raw pivot-table operations.

The gap was closed by:

- enabling the required events infrastructure,
- registering the listener for `RoleAttached` / `RoleDetached`,
- writing through the existing activity-log architecture rather than introducing a parallel audit system.

Regression coverage now includes:
- `tests/Feature/RoleAssignmentAuditTrailTest.php`

This closes the discovered role-assignment/removal audit visibility gap. Workstream Q remains open for the broader security-evidence audit of membership, permission, workflow and other sensitive authorization decisions.

### 37.5 Database-constraint investigation — root-caused and CLOSED (supersedes the earlier rollback)

A `company_user` uniqueness constraint was first attempted as a defense-in-depth measure and rolled back after it broke ~95 tests. That rollback was **not** the final disposition — it was re-investigated from first principles rather than left as an accepted gap.

Root cause identified precisely: `CompanyFactory`'s `afterCreating` hook silently attaches `User::first()` to every company it creates (itself via the safe `syncWithoutDetaching`), and 59 test files separately called the raw, non-deduplicating `attach()` on the same `Company::users()`/`User::companies()` relation for a user that could coincide with `User::first()` in that test's data, producing a genuine second pivot row. This was a test-construction pattern, not a production one — every production code path (`UserController`, `CompanyController`) already used `sync()`/`syncWithoutDetaching()` exclusively, confirmed by grepping the whole `app/` tree for any raw `attach()` call on this relation and finding none.

Final remediation:
- All 94 occurrences of `->companies()->attach(...)`/`->users()->attach(...)` across 59 test files (verified single-argument, no pivot data) were converted to `syncWithoutDetaching(...)`, the same safe pattern already used in production code and by the factory hook itself.
- The `unique(['company_id', 'user_id'])` constraint migration (still deduping any pre-existing rows before enforcing) was reinstated.
- Full suite verified GREEN with the constraint actively enforced, on both SQLite and a real local MariaDB 12.3 instance.

**Workstream R is CLOSED / VERIFIED**, not an open investigation item. The rollback recorded in an earlier version of this section must not be treated as the final state.

### 37.6 Current Workstream J–AA status

The phase remains open overall, but more of it is now closed than the immediately-prior checkpoint recorded.

| Workstream | Current status |
|---|---|
| J — Permission Matrix | OPEN — no standalone written matrix artifact yet |
| K — Service-layer Authorization | VERIFIED — `UserController`/`RoleController`/`CompanyController` confirmed as the only mutators of role/membership/company-scoped-role state; no bypass found |
| L — Workflow / Approval Foundation | OPEN |
| M — Approval State Machine + Idempotency | OPEN |
| N — Segregation of Duties | ANALYZED, NOT IMPLEMENTED — existing Super-Admin-gated role-management access already constitutes a real control; "last Admin of a Company" removal classified THEORETICAL/NOT ACTIONABLE given Super-Admin's existing bypass, not guessed as new policy |
| O — API Authorization Parity | VERIFIED — `check-permission`/`SetApiCompany`/`AuthenticateDevice` route through the same team-context resolver as web, so parity is structural |
| P — Offline/Sync Authorization Parity | VERIFIED — `AuthenticateDevice` uses the same resolver mechanism; no separate authorization model found |
| Q — Audit / Security Evidence | PARTIALLY VERIFIED — role assignment/removal gap fixed; direct-permission-grant and company-membership-change audit trails remain open |
| R — Database Constraints / Consistency | **CLOSED / VERIFIED** — `company_user` uniqueness constraint enforced on SQLite and real MariaDB, root cause of the earlier test breakage fixed at the source (see §37.5) |
| S — Concurrency / Race Conditions | OPEN |
| T — Permission Cache / Team Isolation | VERIFIED for the discovered defect class — wildcard cache/context invalidation fixed and regression-tested; full exhaustive adversarial coverage across every entry point still open |
| U — Cross-company Adversarial Attack | PARTIALLY VERIFIED — `tests/Feature/CompanyAuthorizationIsolationTest.php` and the pre-existing `UserImpersonationTest` suite cover a meaningful slice; a dedicated, exhaustive adversarial pass beyond that was not performed as its own exercise |
| V — Functional Authorization / Workflow UX | OPEN |
| W — Test Teeth / Test Quality | OPEN as a standalone audit (individual fixes this pass were verified fail-first) |
| X — Full Regression | PARTIALLY VERIFIED — current full suite 1435/1435 GREEN, including the enforced `company_user` constraint; final closure regression remains open until J, L/M, S, V/W, Y/Z/AA are complete |
| Y — Domain / Product Analyst Pass | OPEN |
| Z — Final Adversarial Release Audit | OPEN |
| AA — Roadmap / State / Decision Reconciliation | IN PROGRESS — this document is part of that reconciliation |

### 37.7 Mandatory closure rule

**Do not close the phase merely because the full test suite is green or because the test count increased.**

The Organization Authorization & Workflow Governance phase may be marked **CLOSED** only when:

- J–AA are substantively addressed;
- all evidence-backed security defects are fixed or explicitly quarantined;
- real upgrade-path migration remains GREEN;
- real MariaDB verification remains GREEN;
- API and Offline/Sync authorization parity is verified;
- concurrency/atomicity review is complete;
- audit/security evidence is complete for the defined sensitive actions;
- adversarial cross-company testing is GREEN;
- test-quality review confirms meaningful negative/fail-first coverage;
- full regression remains GREEN;
- roadmap, state, execution log and decision register match the actual repository.

### 37.8 Current autonomous execution rule

For this phase, the latest execution contract supersedes older generic checkpoint guidance:

> **Run J–AA as one large coherent autonomous engineering batch.**

Routine implementation details, test design, migration mechanics and small reversible refactors do not require owner confirmation.

The agent should continuously execute:

`analyze -> reproduce -> fail-first -> implement -> targeted regression -> full suite -> adversarial review -> durable documentation -> continue`

Only a genuinely new business/architecture decision that cannot be derived from locked decisions and repository evidence is a stop condition.

A failure in one workstream must not unnecessarily idle unrelated safe work.

### 37.9 Next execution objective

The immediate objective is **not another migration rewrite**. K, N, O, P, R, and T are now verified or substantively addressed (see §37.6). The next large batch begins from the current 1435/1435 GREEN baseline (constraint-enforced) and completes the remaining closure scope, with particular attention to:

1. J — a standalone, written permission-matrix artifact;
2. L–M — workflow/approval foundation and adversarial state-machine idempotency testing;
3. S — concurrency/race-condition review;
4. V–W — functional authorization UX and a dedicated test-quality/test-teeth audit;
5. U — extending cross-company adversarial coverage beyond the current isolation tests into a full, dedicated pass;
6. Q — the two remaining audit-trail gaps (direct permission grants, company-membership changes);
7. Y — domain/product analyst pass;
8. Z — final adversarial release audit;
9. AA — final roadmap/state/decision reconciliation.

### 37.10 Product roadmap perspective

The project is materially stronger than the raw feature-count suggests because the Financial Foundation is closed and major authorization/sync/integrity foundations have been hardened. However, the overall product roadmap remains far from complete because major strategic areas remain open, including advanced accounting maturity, bank reconciliation, tax operating system, evidence/audit-ready file, zero-accounting-knowledge UX, industry/market intelligence, AI/Copilot, and modular licensing/entitlement architecture.

No overall-project completion percentage is treated as an authoritative roadmap metric in this file; completion should continue to be assessed by verified roadmap scope rather than test count alone.

# 38. Current Verified Override — 2026-09-21 (Workstream S Closure + Security Hardening Pass)

## 38.1 Workstream S — Concurrency / Race Conditions: now CLOSED / VERIFIED

§37.6 previously listed **S — Concurrency / Race Conditions: OPEN**. This is superseded: S is now **CLOSED / VERIFIED** across all three of its owner-specified phases (Financial Atomicity, State Transition Inventory, Inventory & Expense Hardening). 15 real, previously-invisible concurrency findings were found and fixed (Payroll, Invoice/AncillaryCost approve, Invoice payment, Document numbering, Settlement double-scheduling, Bank reconciliation match, Cheque-invoice payment, Product quantity, Product average cost, AncillaryCost deletion), using a real two-connection MariaDB test harness (`tests/Helpers/MariaDbRaceTestCase.php`) capable of proving genuine blocking/interleaving behavior, not merely SQLite-level inference. Full detail: Decision Log "Fourth" through "Seventh continuation pass" sections; Execution Log Entries covering Workstream S Phases 1–3. Full suite: 1426 → 1457, zero regressions.

## 38.2 Naming-collision reconciliation: this mission's "Workstream T" is NOT §37.6's Workstream T

**This is a documentation hygiene issue this reconciliation pass is explicitly surfacing, not silently resolving by overwrite.**

Immediately after closing Workstream S, this mission's own running Decision Log and Execution Log opened a **new** workstream and labeled it **"Workstream T — Security Hardening"** (Decision Log "Eighth"/"Ninth continuation pass" sections; Execution Log Entries 67–69). That label collides with §37.6's own, pre-existing **Workstream T — Permission Cache / Team Isolation** (already VERIFIED, a completely different topic: Spatie wildcard-permission cache invalidation across company-context switches). The two are unrelated in scope and neither supersedes the other — this is a pure label reuse, not a scope conflict or a redone piece of work.

**Disposition**: §37.6's Workstream T (Permission Cache / Team Isolation) stands exactly as recorded — VERIFIED, unchanged, not re-touched by this pass. The Decision Log/Execution Log's "Workstream T — Security Hardening" is a distinct, freestanding cross-cutting security sweep that does not belong to the J–AA Organization Authorization & Workflow Governance letter sequence at all (it covers general web-application security hardening — XSS, rate limiting, dependency CVEs, security headers — not organization/authorization/company-scoping). No renaming of already-written Decision Log/Execution Log entries was performed (the churn would be purely cosmetic and risk introducing transcription errors into an otherwise-accurate historical record); instead, any future reference to this pass from roadmap-level documents should call it the **"Security Hardening sweep (2026-09-21)"** rather than "Workstream T", to avoid perpetuating the collision. If a letter is ever needed for it in a future J–AA-style table, use **BB** (after AA), never a letter already assigned in §37.6.

## 38.3 Security Hardening sweep (2026-09-21) — summary

A cross-cutting sweep, run immediately after Workstream S closed, using the same five-role framing (Senior Backend Engineer, Financial Systems Reviewer, Concurrency & Security Auditor, Test Engineer, Domain/Product Analyst). Not part of the J–AA Organization Authorization scope — general application security hardening, applicable regardless of which product phase is current.

**4 real findings fixed**:
1. Stored XSS via CSV-imported Product/Customer/Service names bypassing web-form validation, rendered unescaped in invoice-conflict messages — fixed with output escaping (`e()`) plus import-side denylist validation.
2. No rate limiting on `/login` or `/reset-password` — fixed with `throttle:6,1`, matching the app's own pre-existing convention for every other auth route.
3. 16 known CVEs across `guzzlehttp/guzzle` (direct dependency) and `league/commonmark` (transitive) — fixed via `composer update` within existing version constraints.
4. No defense-in-depth response headers at all (CSP, X-Frame-Options, nosniff, Referrer-Policy, HSTS) — fixed via a new global `SecurityHeaders` middleware.

**1 preventive, non-critical hardening**: `throttle:10,1` added to the device-pairing endpoint for convention-consistency, after explicitly reasoning through and confirming its 36^8-keyspace/15-minute-TTL activation code is not practically brute-forceable even unthrottled.

**1 test-infrastructure fix**: global per-test cache isolation (`Cache::store()->flush()` in `tests/TestCase.php`), needed because Laravel's `throttle` middleware counts all requests within its window and the shared test-client IP could otherwise leak rate-limit state across the whole suite.

**6 areas swept and confirmed already safe**, with documented reasoning rather than either an ignored lead or an inflated finding: SQL injection, mass assignment, file-download path traversal, CSRF, CORS, session cookie flags, user impersonation privilege escalation, device/sync API authentication, SVG-upload file rendering.

Also fixed 7 dev-only `npm audit` findings (build tooling only, no runtime/production impact).

Full detail: Decision Log's "Eighth"/"Ninth continuation pass" sections; Execution Log Entries 67–69. Full suite: 1457 → 1465 across the whole sweep, zero regressions, no schema changes.

## 38.4 Effect on Organization Authorization & Workflow Governance phase closure

None. The Security Hardening sweep is orthogonal to the J–AA closure criteria in §37.7 — it neither advances nor blocks J, L/M, S, U, V/W, Y, Z, or AA.

## 38.5 Further roadmap-vs-repository staleness correction: L/M were already concluded, not "OPEN"

While reconciling §38.1's S correction, found the SAME kind of staleness affecting L/M: §37.6's table still lists **L — Workflow / Approval Foundation: OPEN** and **M — Approval State Machine + Idempotency: OPEN**. This is stale. The Decision Log's "Third continuation pass" (run between the J permission-matrix pass and the S deep-audit passes) explicitly adversarially audited L/M, found and fixed one real CRITICAL defect (**finding #5**: `PayrollController::transition()` created a duplicate `PayrollStatusHistory` row under genuine concurrency — no lock before validating/writing a status transition), disproved one suspected race (`PersonnelRequestController::approve()`/`reject()`, confirmed safe by an idempotent-by-reconstruction mechanism, not accidentally safe), and reached an explicit, tested conclusion: *"No new workflow/approval abstraction is justified. The codebase already has three independently-valid safety patterns for repeat/concurrent status transitions (exhaustive match-table, pessimistic lock, idempotent-by-reconstruction); the gap found and fixed this pass was a missing application of an already-established pattern (the lock), not a missing pattern."* Regression coverage: `tests/Feature/WorkflowApprovalIdempotencyTest.php` (5 tests). Full suite at that point: 1449/1449.

**Corrected status: L — CLOSED / VERIFIED. M — CLOSED / VERIFIED.** Both by the same adversarial-proof standard already applied to R and S (reproduce with two independently-fetched pre-write instances simulating genuine concurrency, not just sequential re-invocation).

**Updated J–AA remaining-open list** (supersedes §37.9's list): **J** (written permission-matrix artifact still not produced as its own document, though the underlying 7 real gaps it found were all fixed), **N** (the "last Admin of a company" policy question, deliberately left to the owner), **Q** (2 of 3 audit-trail types still unimplemented: direct permission grants, company-membership changes), **U** (partially verified only — no dedicated exhaustive adversarial pass beyond the isolation/impersonation tests already in place), **V** (functional authorization UX pass), **W** (standalone test-quality/test-teeth audit, as opposed to the fail-first discipline already applied to every individual fix), **Y** (domain/product analyst pass through all 15 originally-specified example scenarios), **Z** (final adversarial release audit), **AA** (this reconciliation itself — ongoing by nature, updated again with each pass). **J, K, L, M, N(analyzed), O, P, Q(partial), R, S, T(original, Permission Cache), U(partial), X(partial) now have SOME verified disposition recorded; V, W, Y, Z remain fully untouched.**

The phase remains **NOT CLOSED** per §37.7's mandatory closure rule (a green suite alone never closes it) — but materially less is open than §37.6/§37.9 as originally written suggested.

## 38.6 Current Verified Override — 2026-09-21 (Epic Pack 1: J/Q/U/Y closure)

Owner-issued "Epic Work Package 1" batch, run as one continuous pass. All four named workstreams are now closed.

### J — CLOSED
Written permission-matrix artifact produced: `docs/SECURITY_PERMISSION_MATRIX.md`, mechanically generated from `php artisan route:list --json` (454 application routes, every AUTH-ONLY/PUBLIC route individually reviewed). This exercise surfaced **CRITICAL finding #12**: `barryvdh/laravel-translation-manager`'s entire `/translations/*` route group (11 routes) was registered with NO middleware at all -- unauthenticated read/write access to every translation string application-wide. Fixed by setting `config/translation-manager.php`'s route middleware to `['web', 'auth', 'permission:access-super-admin-panel']`.

### Q — CLOSED
Both remaining audit-trail gaps from the earlier pass closed: direct permission grants (`PermissionAttached`/`PermissionDetached` listeners added, mirroring the existing Role listeners) and company-membership changes (`company_user` sync/attach calls in `UserController`/`CompanyController` now explicitly logged). Introducing the new listener surfaced and required fixing 7 `ActivityLogTest` tests whose hardcoded activity counts predated the new `permission_attached` row their own `givePermissionTo('access-super-admin-panel')` setup now incidentally creates -- a correct, expected side effect, not a defect.

### U — CLOSED
Dedicated adversarial cross-company pass (`tests/Feature/AdversarialCrossCompanyTest.php`, `tests/Feature/ActiveCompanyCookieSpoofingTest.php`) re-verified Document/Invoice/Cheque/DocumentFile/Payroll structural protection empirically via real HTTP requests. This surfaced **CRITICAL finding #13, the single most severe finding of this entire mission**: `getActiveCompany()` fell back to reading the raw, unvalidated incoming request cookie whenever `config('active-company-id')` was null -- exactly the state `DefaultCompany` middleware leaves things in after correctly REJECTING a cookie naming a company the user isn't a member of (`Cookie::forget()` only queues an outgoing-response change; it cannot alter the current request's already-parsed cookie). Any authenticated user could set `active-company-id` to an arbitrary company id and gain full read/write access to that company's entire `FiscalYearScope`-scoped dataset (31 models). Fixed by removing the cookie-fallback branch entirely -- every real request path already derives the config value correctly through validated middleware before this helper is ever called. See AGENTS.md guardrail #30.

### Y — CLOSED
`tests/Feature/DomainBusinessScenariosTest.php`: 7 realistic, end-to-end combined business scenarios (sell with mixed cash/cheque payment + weighted-average COGS; partial sales return; purchase with ancillary cost; guarantee-cheque lifecycle; approved-invoice immutability + safe re-approval; payroll calculation through the full workflow; a cross-reference to Workstream S's already-exhaustive concurrency proofs rather than duplicating them).

### Updated status
**J, Q, U, Y move from open/partial to CLOSED.** Combined with §38.1/§38.5's corrections (S, L, M also closed), the remaining genuinely open items are: **N** (owner policy decision, not a defect), **V** (functional authorization UX, untouched), **W** (standalone test-quality audit, untouched), **Z** (final adversarial release audit, untouched), **AA** (ongoing reconciliation). The phase is closer to done than §37.6's original table reflects, but §37.7's mandatory closure rule still applies -- V/W/Z remain fully untouched and the phase is **NOT CLOSED**.

Full suite across this batch: 1465 (post-Workstream-T baseline) → 1488, +23 tests, 2 CRITICAL vulnerabilities found and fixed, zero regressions.

## 38.7 Current Verified Override — 2026-09-21 (Epic Pack 2 redirected: P1.1/P1.2/P1.3 audit, not rebuild)

The owner's "Epic Work Package 2" prompt requested building Payment Allocation (P1.1), Bank Reconciliation (P1.2), and AR/AP Ageing (P1.3) from scratch. Repository verification (before writing any code) found **all three already fully implemented and tested** since 2026-09-14/15, under D-070/D-078-D-082 -- with a polymorphic `PaymentAllocation` schema (shared with `SettlementInstallment`) rather than the plain `invoice_id` FK the prompt assumed. This is a prompt-vs-repository staleness case, the same class already hit for L/M (§38.5). Redirected, with the owner's explicit agreement, to a deep 4-persona audit of the existing implementation instead of a duplicate rebuild.

**Audit result**: 1 CRITICAL finding (#14) -- `BankReconciliationController::match()`/`unmatch()` had no cross-company ownership check on the route-bound `BankStatementLine` (no `FiscalYearScope` of its own) or on `match()`'s request-body `transaction_id` (`Transaction` also has no `FiscalYearScope`) -- the 8th instance of the Workstream J defect class, missed originally because the vulnerable id came from the request body, not a route parameter. Fixed; see Decision Log's "Tenth continuation pass" for full detail, and AGENTS.md guardrails #31-#32.

A second vector (`PaymentAllocationController::allocate()`'s body-sourced `invoice_id`) was independently tested and confirmed ALREADY safe (`Invoice` does carry `FiscalYearScope`) -- not every body-sourced id was assumed unsafe by pattern-matching; each was verified individually.

**Four-persona sign-off**: Financial Domain Auditor confirmed `SUM(transactions.value)=0` holds for both `allocate()` and `matchLine()` (neither posts a new document); Security Engineer found and fixed CRITICAL #14, confirmed existing `lockForUpdate()` concurrency protection (Workstream S findings #7, #10) still holds; Architect confirmed all three controllers are thin and fully delegate to their services; Lead Developer confirmed full suite 1488 → 1491, zero regressions.

No schema changes were required -- the existing schema and services were sound; only the one missing authorization check needed fixing.

## 38.8 Current Verified Override — 2026-09-21 (Epic Pack 3: P2 closed, P3 built as 5 new subsystems)

Owner-issued "Epic Work Package 3", later superseded mid-pass by a larger "Master Phase Prompt" spanning P2/P3/P4. Per audit-first strategy and owner confirmation ("P3 before P4, one subsystem at a time, no stopping"):

**P2 (Cheque Settlement / Guarantee Cheques) — closed.** Already existed (D-070, 2026-09-15). One CRITICAL finding: `ChequeService::returnToCustomer()` had no guard against an outstanding (partially-settled) `SettlementObligation` -- fixed, mirroring the existing `delete()` guard's "no payment yet / fully settled" exceptions. The owner's request for an explicit "Fully Settled" Cheque status was flagged as conflicting with D-070's own locked "three independent lifecycles, never stored/denormalized settlement state" design and was NOT implemented without an explicit new Decision -- `SettlementService::isSettled()` already provides the same semantic live-computed.

**P3 (Accounting Maturity) — all 5 subsystems built, tested, and verified. NONE existed before this pass** (unlike P1/P2, this was genuinely new territory -- only a plain `companies.currency` string field existed beforehand):
- **P3.1 Analytic Dimensions**: `analytic_dimensions` + `analytic_dimension_transaction` (transaction-level, many-to-many tagging: Branch/Project/Cost Center/Sales Channel), `AnalyticDimensionService`.
- **P3.2 Payment Terms & Installments**: reuses D-070's own `SettlementObligation`/`SettlementInstallment` ledger (source=Invoice, not a second parallel system) via a newly-generalized `SettlementService::createObligation()`. Surfaced and fixed a real CRITICAL concurrency gap in that generalized method (no lock existed since D-070's original implementation) with a real two-connection MariaDB proof.
- **P3.3 Recurring Operations**: one generic mechanism for recurring invoices AND recurring "expenses" (no separate Expense entity exists in this app), `recurring:generate` Artisan command, duplicate-generation guard proven by direct repeat-call test.
- **P3.4 Multi-Currency Foundation**: deliberately scoped as additive metadata (`transactions.currency_id`/`foreign_value`/`exchange_rate`) -- `transactions.value` and the `SUM=0` invariant are completely untouched. Currency/rate CRUD + conversion + FX-difference math; NOT a full multi-currency Invoice/Payment rewrite (that remains future scope if ever wanted).
- **P3.5 Fixed Assets**: straight-line depreciation, one real balanced Document posted per period, capped at the depreciable amount across all periods, disposal lifecycle.

**Two real self-introduced/pre-existing bugs found and fixed via the build process itself** (not via a targeted audit -- via actually building and testing): a MySQL 64-character identifier-length violation in a new pivot table's unique constraint (invisible on SQLite, only caught because the real-MariaDB race tests happened to run the same migration set and failed); an `updateOrCreate()` vs `'date'`-cast lookup mismatch in `ExchangeRate`. See AGENTS.md guardrails #33-#35.

**Four-persona sign-off**: Financial Auditor confirmed `SUM(transactions.value)=0` holds in every new posting path; Security Engineer confirmed 18 cross-company tests across the 5 subsystems plus the CRITICAL concurrency fix; Architect confirmed thin controllers and DRY reuse of D-070's existing ledger; Lead Developer confirmed full suite 1491 → 1535 (+44 tests: 3 for the P2 fix, 41 for P3), zero regressions across the whole pass.

**P4 (Operational Depth -- warehouse) has not been started.** Per the same audit-first discipline, its existing-infrastructure state (WarehouseService, WarehouseProductStock, etc.) needs to be checked before any new code, exactly as P1/P2 turned out to already exist and P3 turned out not to.

## 38.9 Current Verified Override — 2026-09-21 (P4 complete: 8 subsystems, one CRITICAL cross-company finding)

Audit-first confirmed P4 was genuinely new territory too (only `WarehouseService`'s basic create/update/transfer existed).

**Architectural fork surfaced and resolved with the owner before writing code**: the owner's P4.3 spec (reserve stock without deducting real inventory until delivery) conflicts with this app's current behavior (stock moves immediately at Invoice approval, exercised throughout Workstream S). Owner chose a **separate additive layer** -- reservations/receipts/deliveries/lots/serials all coexist with, and never touch, the existing Invoice-driven stock movement. `WarehouseProductStock.quantity` remains the sole source of truth.

**All 8 P4 subsystems built and tested**:
- P4.1/P4.2 (Purchase Receipt / Sales Delivery): `goods_receipts`/`delivery_notes`, audit-trail confirmation logs only, `InvoiceFulfillmentService`.
- P4.3 (Stock Reservations): `stock_reservations`, `StockReservationService`, availability = on-hand minus active reservations, lock-protected against over-reservation.
- P4.4-P4.6 (Barcode/Lot/Serial): `products.barcode`, `product_lots` (FEFO), `product_serial_numbers`, `ProductTrackingService`.
- P4.7/P4.8 (Valuation/Slow-Moving): `InventoryReportService`, pure read reports, no new tables.

**CRITICAL finding**: `InventoryReportService`'s first draft leaked cross-company stock data (`WarehouseProductStock` has no `FiscalYearScope`) -- caught by the report's OWN fail-first cross-company test before it ever reached a route. Fixed with `whereHas('warehouse')`. Two further self-introduced bugs (wrong polymorphic type string, a signed-vs-absolute `Carbon::diffInDays()` mistake) were also caught and fixed via the same test-writing process. See Decision Log's "Twelfth continuation pass" and AGENTS.md guardrail #36.

**Four-persona sign-off**: Financial Auditor confirmed P4 introduces zero new financial-posting paths (all non-financial tracking); Security Engineer found and fixed the CRITICAL leak, confirmed lock protection on the two over-allocation-shaped guards (`reserve()`, `consumeFromLot()`); Architect confirmed the additive-layer boundary held (no existing `ProductService`/`InvoiceService` method was touched); Lead Developer confirmed full suite 1535 → 1566 (+31 tests), zero regressions.

**P2→P4 combined total for this "Master Phase Prompt": full suite 1491 → 1566 (+75 tests), 3 CRITICAL findings (guarantee-cheque return, SettlementObligation race, InventoryReportService cross-company leak), 4 smaller self-introduced bugs caught before shipping, 1 architectural fork resolved with the owner rather than decided unilaterally, zero breaking schema changes.**

## 38.10 Current Verified Override — 2026-09-22 (P5 complete: 3 read-only dashboards, one sign-convention bug found and fixed)

Audit-first confirmed P5.3 (AR/AP Ageing) is already fully satisfied by the existing `AgeingService` (P1) and P5.5 (drill-down) is satisfied by a thin new `transactionsForPeriod()` method rather than a separate subsystem -- neither was rebuilt.

**All 3 new P5 subsystems built and tested**:
- P5.1 (Cash Flow Dashboard): `CashFlowReportService`, net inflow/outflow/period over every bank/cash subject (`PaymentService::settlementSubjectIds()`, reused). Deliberately scoped as net cash-movement, not a full 3-way IFRS statement -- this app's chart of accounts has no support for that classification.
- P5.2 (Cheque Risk Dashboard): `ChequeRiskDashboardService`, buckets outstanding cheques into due-soon/overdue/bounced by direction, pure read over the existing D-070 cheque lifecycle.
- P5.4 (Profitability by Analytic Dimension): `ProfitabilityByDimensionService`, reuses `AnalyticDimension`'s transaction-tagging (P3.1) and the existing config-based revenue/expense subject classification.

**Bug found and fixed via the report's own fail-first test**: `CashFlowReportService::report()`'s first draft had inflow/outflow backward -- `value > 0` as inflow, following a naive "positive = money in" assumption. This app's actual convention posts a cash receipt as `+amount` to the customer subject and `-amount` (a debit) to the cash/bank subject, and a debit INCREASES an asset's balance -- so `value < 0` is the real inflow for a cash/bank subject specifically, the opposite convention from a revenue/liability subject. Fixed by swapping the CASE conditions. See Decision Log's "Thirteenth continuation pass" and AGENTS.md guardrail #37 (also documents a `SeederHelper` test-construction trap found in the same pass).

**Four-persona sign-off**: Financial Auditor confirmed the sign-inversion fix against the app's own posting convention and confirmed profitability/cheque-risk introduce no new posting path; Security Engineer confirmed all 3 services company-scope explicitly with dedicated cross-company tests; Architect confirmed all 3 are pure read reports with a thin controller and correctly reused P1/P3.1 rather than rebuilding; Lead Developer confirmed full suite 1566 → 1576 (+10 tests), zero regressions.

Proceeding next to **P6 (API v1 Platform)** per the owner's confirmed P5←P6←P7 sequencing.

## 38.11 Current Verified Override — 2026-09-22 (P6 complete: /api/v1 namespace, time-boxed tokens, Idempotency-Key, Webhooks, OpenAPI docs)

Audit-first (ground-truthed via a dedicated research pass) confirmed: token infrastructure existed but tokens never expired; no `/api/v1` namespace of any kind; no response-envelope layer; zero generic HTTP Idempotency-Key mechanism (three unrelated "idempotent" concepts already existed for other purposes); zero webhooks; zero OpenAPI tooling.

**Versioning built as a NEW, parallel namespace, not a migration** -- the existing unversioned `/api/*` routes (relied on by the native desktop app and device-sync clients) were left completely untouched; `/api/v1/*` controllers are thin adapters delegating to the same legacy controllers/services, re-shaped through a new `ApiResponds` envelope trait. This is the same additive-layer principle the owner chose for P4.3, applied here as an obviously-safe default rather than something requiring a fresh decision.

**All 5 P6 subsystems built and tested**:
- P6.1 (versioned namespace + envelope): `/api/v1/*` reusing legacy permission strings, `{data, meta}`/`{message, errors}` standard shape via `ApiResponds`.
- P6.2 (time-boxed tokens): optional `expires_at` on token creation, verified against Sanctum's own per-token expiration enforcement (independent of the still-`null` global config).
- P6.3 (Idempotency-Key): new middleware + table, atomicity proven with a real two-connection MariaDB race (`IdempotencyKeyRaceTest`).
- P6.4 (Webhooks): new `webhooks`/`webhook_deliveries` tables, HMAC-signed delivery job with self-managed retry (driver-independent, since this app defaults to `QUEUE_CONNECTION=sync`), fed by two new domain events (`InvoiceStatusChanged`, `ChequeStatusChanged`) that `InvoiceService`/`ChequeService` fire with zero awareness webhooks exist.
- P6.5 (OpenAPI docs): `dedoc/scramble`, zero manual annotation, documents the full API surface at `/docs/api`, gated behind a new `viewApiDocs` Gate in every non-local environment.

**One real test-infrastructure trap found (not a product bug)**: a single test method authenticating as more than one identity in one method silently keeps authenticating as the FIRST identity, due to two distinct Sanctum/Laravel internals (`RequestGuard`'s per-instance user caching, and Sanctum's guard checking the web session before the bearer token). Fixed by giving each identity its own test method, matching this codebase's own pre-existing convention. See AGENTS.md guardrail #38.

**Four-persona sign-off**: Financial Auditor confirmed zero financial-posting paths were touched (the two new domain events fire only after their transaction commits, never gating anything); Security Engineer confirmed idempotency's real-race atomicity, webhook secrets shown once only, and `/docs/api` gated everywhere but local; Architect confirmed v1 controllers are pure delegation with zero duplicated logic and the native-app surface is fully untouched; Lead Developer confirmed full suite 1576 → 1601 (+25 tests), zero regressions.

Proceeding next to **P7 (Role & Workflow Governance)** per the owner's confirmed P5←P6←P7 sequencing.

## 38.12 Current Verified Override — 2026-09-22 (P7 complete: Segregation of Duties, Approval Chains, Posted Document Lock confirmed -- closes the P5+P6+P7 arc)

Audit-first found and corrected a real error in this project's own Decision Log: a prior "Workstream N" entry claimed self-approval prevention was "already governed by the Financial Foundation phase's approval guards" -- false, confirmed by exhaustive search across every approve/reject/transition entry point. See Decision Log's "Fifteenth continuation pass" for the full correction.

Two genuine business-policy forks were surfaced to the owner (mirroring the P4.3 additive-layer precedent) rather than decided unilaterally: how strictly to enforce self-approval prevention (most target SMB customers are a single bookkeeper who both creates and approves), and what should trigger a multi-step approval chain. Owner's decisions: **P7.2 opt-in per company, default OFF**, self-approval always logged regardless; **P7.1 amount threshold, Invoice only**, with a separate `invoices.final-approve` permission for the second step.

**All 3 P7 subsystems addressed**:
- P7.2 (Segregation of Duties): new `created_by` tracking added to AncillaryCost/PersonnelRequest/Payroll (Invoice already had it); `SegregationOfDutiesService` guards Invoice/AncillaryCost/PersonnelRequest/Payroll approval at the exact moment of approval, before any financial effect; settings stored via the existing `Config`/`ConfigLoader` infrastructure through a new purpose-built `WorkflowGovernanceController` (not the Subject-picker-shaped `ConfigController`).
- P7.1 (Approval Chains): new `InvoiceStatus::PENDING_FINAL_APPROVAL` (verified additive against every exhaustive enum match in the codebase first); an invoice at/above a configurable per-company threshold requires a second, independent approval (separate `invoices.final-approve` permission, different user than the first step) before it actually posts; below threshold (or no threshold configured, the default) is completely unchanged from before this feature existed.
- P7.3 (Posted Document Lock): confirmed via audit as a real, tested, LOCKED boundary (D-084 through D-089) around the Invoice/Payment/PaymentAllocation graph -- but scattered/per-feature, not a generic mechanism (`DocumentService` itself has no such check). Extending it to every document type was out of scope for this pass; explicitly noted so a future pass doesn't re-derive this finding.

**Four-persona sign-off**: Financial Auditor confirmed the chain's first step touches no financial posting at all and corrected the Decision Log's own prior wrong claim rather than propagating it; Security Engineer confirmed the guard runs before any financial effect in all 4 call sites and self-approval is logged even when not enforced; Architect confirmed the new settings reuse existing `Config` infrastructure and the new enum case was verified safe against every exhaustive match; Lead Developer confirmed full suite 1601 → 1625 (+24 tests), zero regressions.

**This closes the P5+P6+P7 "Master Phase Prompt 2" arc (Reporting 2.0, API v1 Platform, Role & Workflow Governance) in full.**

## 38.13 Current Verified Override — 2026-09-22 ("Master Phase Prompt 3" kickoff: P10 confirmed complete + hardened, P9 audited + Moadian hardened, P8 not yet started)

**Read this section, not §15, for the sync subsystem's actual state.** §15 still says sync scaffolding is "not implemented... greenfield" -- stale. A dedicated audit + a live `--filter=Sync` run (132/132 passing) confirms the entire command-aware sync engine (Outbox/Inbox, idempotent replay, conflict review queue, cursor-based pull, device pairing, server-authoritative numbering leases -- D-071 through D-090) already exists and works. One real gap was found and fixed: `Payment`/`PaymentAllocation` have no `FiscalYearScope`, so `SyncPilotService::apply()`'s uuid lookup wasn't company-filtered -- a cross-company uuid collision could have surfaced another company's payment's existence/version in a sync conflict response. Fixed (new `PaymentAllocation::resolvedCompanyId()`, an explicit check in `apply()`), proven by `tests/Feature/PaymentSyncCrossCompanyTest.php`. No other P10 change was needed.

**P9 (Iran Tax OS) audit found a real, working Moadian e-invoice integration already exists** (`MoadianService`, `jooyeshgar/moadian` v3.0, Company-level encrypted credential storage) -- but it had **zero test coverage** and defaults to the live production tax-authority endpoint. Hardened this pass: (1) **CRITICAL cross-company fix** -- `MoadianHistoryController::index()` leaked every company's tax-submission history (`MoadianHistory` has no `FiscalYearScope`), fixed with `whereHas('invoice')`; (2) first-ever test suite (`tests/Feature/MoadianServiceTest.php`, 10 tests), built by mocking the `Moadian` facade directly since the package uses raw Guzzle internally (`Http::fake()` cannot intercept it) -- no test can ever reach the real government API; (3) confirmed `MOADIAN_BASE_URI` sandbox override already worked via the vendor package's auto-merged config, just undocumented -- documented in `.env.example`, no behavior changed; (4) `MoadianStatus` enum now actually used instead of bypassed raw string comparisons. **Full suite: 1625 → 1638 (+13 across P10's fix and P9's hardening), zero regressions.**

P9's genuinely new subsystems (Tax Profile, effective-dated Tax Rules engine, expense deductibility classification, Evidence Vault, Tax Readiness Score) and P8 (Zero-Knowledge UX) have not yet been built -- audit and hardening of existing infrastructure came first per the owner's explicit instruction.

## 38.14 Current Verified Override — 2026-09-22 (P9 new subsystems complete: Tax Profile, Tax Rules engine, Deductibility, Evidence Vault, Tax Readiness Score)

Built on the hardened Moadian foundation from §38.13, in reuse-over-rebuild order: **Tax Profile** (`companies.vat_registration_status`, additive to the existing flat-column pattern); **Tax Rules engine** (`TaxRule`/`TaxRuleService::vatRateOn()`, a direct structural mirror of `CurrencyService::rateOn()` -- system-wide, not company-scoped, since Iran's VAT rate is government-set, not per-company); **Expense deductibility** (`ancillary_costs.tax_deductible_status`, since `AncillaryCost` is this app's closest existing "expense" concept -- no generic `Expense` model exists); **Evidence Vault** (`TaxEvidence`, polymorphic, SHA-256 tamper detection, stored on the never-web-exposed `local` disk); **Tax Readiness Score** (`TaxReadinessService`, a 4-metric/100-point score persisted per-calculation as a trend, small Blade dashboard with an inline-SVG gauge).

**One real gotcha, not a design flaw**: Eloquent's pluralizer treats "evidence" as already-plural, so `TaxEvidence` derived the wrong table name until given an explicit `$table`. See AGENTS.md guardrail #47.

**The owner's draft specs for this phase assumed a Vue 3 + Inertia.js stack that does not exist anywhere in this codebase** (confirmed: no `inertiajs`/`vue` in `package.json`, no `resources/js/Pages`) -- every other feature in this entire mission is Blade + daisyUI + Alpine.js, so this phase was built the same way, keeping the draft's actual design intent (integrity hashing, isolated storage, N+1-safe queries, composite indexes, per-company isolation) while adapting the implementation to this app's real conventions.

**Full suite: 1638 → 1663 (+25 tests), zero regressions.**

Next: **P8 (Zero-Knowledge UX)**.

## 38.15 Current Verified Override — 2026-09-22 (P8 first increment: UI mode switch + Sell Invoice wizard)

Owner's explicit scope for this pass: start small, not a full multi-screen rollout. Built: a per-user `ui_mode` (Simple/Advanced) preference on Account Settings, defaulting to `advanced` for every existing user (zero behavior change unless opted in); a 3-step guided wizard for creating a brand-new Sell invoice in Simple mode, chosen as the reference pattern for future screens since it's the highest-traffic flow.

**Deliberately minimal-risk architecture**: the wizard is a pure Alpine.js presentation wrapper over the EXACT SAME fields/route/validation/`InvoiceService::createInvoice()` call the advanced form already uses -- `x-show="step === N"` sections driven by an outer `x-data`, nothing else. No backend branch, no new validation rule. Scoped only to a brand-new Sell invoice; editing and every other invoice type are unaffected regardless of mode.

**One real gotcha, not a design flaw**: Blade's inline `@php(...)` directive silently miscompiled specifically because it was the first line of the file -- fixed with the block `@php ... @endphp` form. See AGENTS.md guardrail #50.

**Verification caveat**: the real dev MySQL database isn't reachable from this session, so the wizard's click-through navigation could not be verified in a live browser -- verified instead via HTTP-level assertions on rendered markup and submission behavior. A human should click through it once before treating this as fully proven.

**Full suite: 1663 → 1672 (+9 tests), zero regressions.**

## 38.16 Current Verified Override — 2026-09-22 (P11 new epic: Field Sales & Distribution / "FieldOperations" -- backend-only, mobile/hardware scoped to a separate design document)

Owner proposed a large new epic (route/van sales, promotions, driver settlement, full Android/Flutter + ESC/POS thermal-printer + SmartPOS hardware integration). **A real scope mismatch was flagged before any code was written**: the mobile/hardware portion needs a completely different stack (no Android/Flutter project exists anywhere in this repo -- `nativephp/desktop` is desktop-only) and cannot be verified from this session (no emulator, no physical printer, no payment SDK access). Owner confirmed: Laravel backend here (fully tested), mobile/hardware as a separate standalone architecture document (`Dakhl_FieldOperations_Mobile_Hardware_Spec.md`) for a dedicated mobile team -- not code written in this session.

**Audit-first found the single biggest piece already exists**: "Van Warehouse" needed zero new code. `Warehouse` rows can already be created ad hoc per rep/van, `WarehouseService::transfer()` already does atomic locked stock transfers between any two warehouses, and `InvoiceService::createInvoice()` already deducts stock at the specific warehouse an invoice targets -- a hot-sale invoice against a van's own Warehouse already works today. See AGENTS.md guardrail #53.

**Built**: `SalesRoute`/`RouteCustomer` (route + ordered customer assignment), `VisitPlan` (one per rep per date, validates company membership), `PromotionEngineService` (pure tiered-discount/bonus-goods calculator, never touches Invoice/Document directly -- P4.3's additive-layer principle again), `DailySettlement` (manual cash/POS/cheque entry + a safely-derived "suggested total invoiced" cross-check, Draft→Submitted→Approved lifecycle).

**Full suite: 1672 → 1691 (+19 tests), zero regressions.**

## 38.17 Current Verified Override — 2026-09-22 ("Master Execution Directive" complete: P12.1-P12.4, Moadian reconciliation + Tax Alerts + Seasonal VAT Report + Field Ops Commission/Route Analytics)

Owner bundled 4 independent Epics into one directive with a hard stack constraint (Blade/Alpine/daisyUI only), mandatory `FiscalYearScope` on every new model, mandatory tests per feature, a zero-regression gate, and 2 explicitly-required cross-company security tests (`TaxAlert`, `RepCommission`). All 4 built sequentially, each with its own full-suite regression run before starting the next -- see the Decision Log's own "P12" section for full narrative detail, findings, and per-Epic test counts. Summary:

- **P12.1** `MoadianReconciliationService` + `SyncMoadianStatusJob`/`moadian:sync-status` -- re-queries live Moadian status for pending invoices, recalculates `TaxReadinessLog` only on an actual change. Found and fixed a real `latestOfMany()` ordering-tie bug (guardrail #54).
- **P12.2** `TaxAlert` (company-scoped, NOT Laravel's per-user Notifications -- a deliberate, documented deviation from the literal draft) + `TaxAlertEngineService`, 3 rules reusing P9.7's existing readiness signals, bell/drawer nav widget. Found a Spatie team-scoped-permission-grant test gotcha (guardrail #55).
- **P12.3** `SeasonalTaxReportService` -- real Jalali quarters (the owner's own draft's quarter labels were garbled and were corrected, not copied), CSV/PDF export via the existing `ReportExportService` registry, explicitly labeled as an unverified-against-TTMS approximation. Found a `selectRaw()`-vs-`$casts` type error (guardrail #56).
- **P12.4** `RepCommissionEngineService` (tiered, APPROVED-settlements-only) + `RouteAnalyticsService` (visit completion / avg invoice value / cash collection ratio), one combined dashboard. A previously-documented bug class (guardrail #35's `updateOrCreate()` date-cast mismatch) recurred in a new context and was caught immediately by pattern recognition; both cross-company tests were strengthened with a positive control after realizing the negative-only assertion would have passed vacuously against the pre-fix broken query.

**Full suite across all 4 Epics: 1691 → 1725 (+34 tests), zero regressions.** Git identity remains unset in this environment -- none of this mission's work has been committed yet; the owner must run `git config` themselves before any of it can be committed.

**Superseded by §39 below**: git identity has since been set, the entire P0-P12 backlog has been reconstructed into 22 atomic commits plus a Release Readiness Audit pass (3 more commits, 1728 tests), and a local backup mirror exists at a separate drive. See §39 for the current state and the next execution phase.

# 39. Product Strategy Execution Roadmap — 2026-09-22 (Backend Foundation Complete → Business Operating Platform)

## 39.0 Framing correction — this is sequencing, not a pivot

The owner's prompt for this pass described this as a "strategic pivot" discovered via fresh competitor research. Before writing anything else: **it is not a pivot.** Every element of it — Simple/Advanced Mode as two experiences over one Domain Layer, an AI/Copilot layer that "must act through the same services, permissions and audit trail as human users," a Provider/Adapter abstraction for external data (already specified for Industry Intelligence and directly reusable for e-commerce integrations), and a full Modular Licensing/Entitlement model naming Accounting/Sales/Inventory/Cheque/Tax/Moadian/CRM/HR-Payroll/Market-Intelligence/AI as independently-activatable modules — is **already written into this Decision Log's foundational "Product Identity" section**, dated to the very start of this mission, long before P0 ever began.

What actually changed this pass is not the direction but two real things:
1. **The backend is now mature enough to build the UX/AI/Integration layers on top of** (1725→1728 tests, financial integrity/concurrency/security hardening closed, multi-tenancy real, sync foundation real) — so this work is now sequenced first, not deferred behind more backend phases.
2. **Fresh 2026 external research** (below) validates the existing decisions with concrete evidence from real products and gives specific, current architectural patterns to implement them with — not permission to change direction, but data to execute the existing direction well instead of by guesswork.

This distinction matters for how future agents should read this section: do not treat §39 as authorization to revisit or "improve on" the Decision Log's Product Identity, AI/Copilot, or Modular Licensing sections. Treat it as their execution plan.

## 39.1 Research findings (external, 2026-09-22)

Three research passes, summarized; full findings available in this session's transcript if deeper detail is needed later.

**Open-source ERP/accounting architecture** (ERPNext, Odoo, Akaunting, Invoice Ninja):
- ERPNext's DocType system (define once, get schema+API+UI+permissions) is the single biggest reason it scaled as a project -- but the same generic power is exactly what reviewers cite as "overwhelming," "steep learning curve." **Lesson: exposing raw configurability by default is the failure mode, not power itself.**
- Odoo proves a paid/advanced tier doesn't need a forked codebase -- Enterprise is just more modules on the same Community core. Its most common complaint is module sprawl/"overkill for small business," reviewers explicitly telling sub-$1M businesses to look elsewhere.
- Akaunting (Laravel, directly comparable to Dakhl) scopes itself small deliberately: core app + an app-store-style module package. Its complaint pattern is the opposite of Odoo's -- gating too much of the *free* tier's reporting/integration depth erodes the "simple but complete" promise.
- Invoice Ninja shows narrow scoping (invoicing-only, not full accounting) works but only when the user's actual need stays narrow -- a caution against under-scoping Simple Mode if real users need more than the narrowest flows.
- **Cross-cutting rule for §39.3 (User Simplicity Layer): Simple Mode must be a thinner UI/workflow surface over the exact same Domain/Service Layer, never a second, parallel data model or a second set of business logic** -- this is already this codebase's own established pattern (P8.2's wizard, guardrail #51: "zero field names/routes/backend validation changed, never fork the underlying business logic per UI mode") and research confirms it is the only pattern that has actually worked elsewhere.

**AI-native accounting products** (QuickBooks Intuit Assist, Xero JAX, Zoho Books Zia, ERPClaw):
- All three mature incumbents embed AI output (anomaly flags, forecasts) **directly inside existing screens** (reports, bill-approval, reconciliation) -- none of them ships AI primarily as a standalone chat panel. A standalone chatbot is the weakest-value pattern found across all research.
- The highest-confidence AI value today is **anomaly detection + forecasting over existing transaction data**, and *proactive* alerts surfaced before a problem compounds (Xero screens every bill for unusual amounts/changed bank details *before* payment) -- not autonomous, unsupervised transaction posting. No mature product does the latter.
- ERPClaw (github.com/avansaber/erpclaw) was specifically investigated as a possible architecture reference. Verdict: **an interesting pattern, not a maturity benchmark** -- ~7 months old, 94 of ~97 commits from one account, no evidence of production deployment, a GPL-vs-MIT license inconsistency between its repo and its own promotional material. Its one genuinely reusable idea: the AI layer submits structured arguments through the *same validated API/service path* a human would use, never writing directly to the ledger. This is not a new idea for Dakhl -- it is exactly what the Decision Log's AI/Copilot section already requires ("AI must act through the same services, permissions and audit trail as human users") and exactly the pattern this codebase's protected-core review process already enforces for `InvoiceTransactionBuilder`/`SettlementService`/etc.

**Integration architecture & licensing**:
- The recommended pattern -- a thin platform adapter (Shopify, WooCommerce, a local marketplace) translating each platform's webhooks into canonical domain events (`OrderCreated`, `InventoryChanged`, `CustomerUpsert`, `PaymentReceived`) consumed by one shared sync engine, webhook-driven with polling reconciliation as a safety net, idempotency via a dedup key on every inbound event -- **is not a new design for this codebase to invent.** It is, almost field-for-field, the existing D-076 through D-080 offline-sync architecture already built and tested (`SyncOutboxService`/`SyncInboxService`/`SyncConflictService`, financial-event-identity idempotency, explicit conflict surfacing instead of silent overwrite). §39.4 should be scoped as *extending* that existing infrastructure to external platforms, not designing a parallel one.
- Tiering research (ERPNext GPL "always free" core, Odoo's Enterprise-is-more-modules pattern, Frappe Cloud's usage-based hosting) supports gating **scale/operations** (multi-company consolidation, additional integration connectors beyond the first, advanced reporting, API rate limits, hosted convenience) while keeping **core bookkeeping, single-company use, and data export free** -- this already matches the Decision Log's Modular Licensing section almost exactly; no change needed there, just confirmation.
- **Real, severe risk surfaced by research and worth recording explicitly**: HashiCorp, Redis, and MongoDB all triggered community forks (OpenTofu, Valkey) after gating previously-free functionality; Redis and Elastic reversed their relicensing, HashiCorp did not and the company itself no longer exists independently. **Rule for §39.5: never regate a capability that free/open-source users already had.** Decide the free/paid boundary once, before the Integration Hub or any module ships, not after.

## 39.2 Phase R — Release Hardening (near-complete)

- **R-01 First-login security**: default admin (`admin@dakhl.local` / `1234`) ships on every install by deliberate owner decision (2026-09-14), documented as a known, intentional simplification -- but the same decision explicitly requires a forced-password-change-on-first-login flow before any public release (this was already tracked as "P0-02," status: not started). Scope: a `users.must_change_password` flag (defaulted true for the seeded admin only), a middleware that redirects to a change-password form until cleared, tests proving the redirect fires/clears correctly and that it does not affect normal users. Small, correctly deferred until now since it blocks nothing else.
- **R-02 Desktop production error handling**: **DONE** this pass -- `NativeAppServiceProvider::phpIni()` now ties `display_errors` to `config('app.debug')` instead of hardcoding it on; `log_errors` stays on unconditionally. See AGENTS.md-adjacent commit `640b7f75`. 3 tests added.
- Also closed this pass: `7zip-bin` Electron packaging dependency (previously found, explicitly left unfixed pending investigation -- now fixed and verified), git hygiene (`.gitignore` for `.claude/`/`build/`, scratch files removed), a local Git mirror backup (`D:\Backups\Dakhl.git`, verified byte-for-byte via a real recovery clone).
- **Remaining in Phase R**: only R-01.

## 39.3 Phase U — User Simplicity Layer (next priority)

The most important open phase per the owner's own framing: "someone with zero accounting knowledge must be able to use Dakhl." P8 built the *mechanism* (per-user `ui_mode`, one wizard) but only applied it to one screen.

- **U-01 Home Dashboard Redesign**: replace (for Simple-mode users only -- Advanced mode keeps the current dashboard unchanged) the current accounts/ledger-oriented home with a "Today" view (today's sales/expenses/stock/due dates) plus a small, fixed set of quick actions (New Sale, Receive Payment, Pay Expense, New Customer). Architecture: this is a presentation-layer change reading the exact same aggregates `ReportingDashboardController`/`HomeController` already compute -- no new domain queries, no new tables.
- **U-02 Simple Mode, extended for real**: currently one wizard (Sell Invoice). Extend the exact same pattern (guardrail #51: pure Alpine `x-show`/`step` wrapper, zero field/route/validation changes) to Buy Invoice, Payment receipt, Expense entry, New Customer -- the highest-frequency flows for a non-accountant user. Each is its own small, bounded pass, not one large rewrite.
- Explicitly **not** in scope for U: any new business logic, any new database column beyond what a wizard might already need, any change to Advanced mode's behavior.
- **Risk**: scope creep into "redesign everything" -- the research lesson from Odoo/ERPNext is precise here: the failure mode is exposing raw power by default, not simplifying too little. Ship one flow at a time, verify with a human clicking through it in a real browser (this codebase's own established gap -- P8.2's wizard was never manually browser-tested, only HTTP-assertion-tested, per its own roadmap note), then move to the next.

## 39.4 Phase AI — Financial Copilot (architecture only this phase; no implementation yet)

Per the Decision Log's existing AI/Copilot section, extended with the concrete architecture research now supports:

- **AI-01 Financial Q&A**: a service that answers questions like "why did my profit drop?" by composing *existing* report services (`CashFlowReportService`, `ProfitabilityByDimensionService`, `TaxReadinessService`, etc.) -- never a raw LLM-over-database query. The LLM's job is natural-language framing of results these services already compute correctly, not independent computation.
- **AI-02 Proactive alerts**: extends the exact mechanism P12.2 already built (`TaxAlertEngineService`/`TaxAlert`/the bell widget) with new rule types (e.g. "customer hasn't ordered in N days," "expense category spiked vs. historical average") -- this is additive rule authoring on an already-shipped, already-tested alert engine, not a new subsystem.
- **AI-03 Assisted workflows** (e.g. "create an invoice for Ahmad for 20 million"): explicitly the *last* AI increment, not the first -- and per both the Decision Log and this pass's research (ERPClaw's one defensible pattern), the AI must submit structured arguments through the exact same `InvoiceService::createInvoice()`/validation path a human filling the form would use, with the same confirmation step before anything posts. Never a direct-to-ledger write path.
- **Explicitly not in scope for this phase**: any implementation. This phase is architecture-and-interface design only, to be scoped as its own future execution pass once Phase U ships and the AI provider/credential-handling pattern (mirrors the Decision Log's Market Data "Managed Provider vs. Bring Your Own API" split, encrypted credentials, never leaked into logs/UI) is worked out in detail.

## 39.5 Phase Integration — Integration Hub (architecture only this phase; no implementation yet)

- **Decision**: build as a generic Provider/Adapter layer (per-platform adapters translating to canonical domain events: `OrderCreated`, `InventoryChanged`, `CustomerUpsert`, `PaymentReceived`), consumed by **the existing sync infrastructure** (`SyncOutboxService`/`SyncInboxService`/`SyncConflictService`, the financial-event-identity idempotency pattern from D-077/D-078, explicit conflict surfacing from D-080) -- not a new parallel sync mechanism. This is the single most load-bearing architecture decision from this pass's research: Dakhl has already built and hardened exactly the infrastructure an e-commerce integration needs, for a different reason (offline desktop sync). Reuse it.
- **INT-01 through INT-04** (Order/Inventory/Customer/Payment Sync): each is one adapter + canonical-event mapping, not one bespoke integration per platform. First target platform to be decided later (owner has not yet specified Shopify vs. WooCommerce vs. a local Iranian marketplace as the first real connector).
- Webhook-driven with polling reconciliation as a fallback (webhooks are "at-least-once, tells you what changed not the new value" -- unreliable alone per research).
- **Explicitly not in scope for this phase**: any implementation, any specific platform choice, any credential/OAuth flow design.

## 39.6 Phase Monetization/Licensing (documentation-only; matches existing Decision Log section, no changes needed)

Research confirms the Decision Log's existing Modular Licensing/Subscription section (module-level entitlements, Subscription/Entitlement/License as three separate concepts, expired-module-becomes-read-only not deleted) already matches the pattern real open-source-rooted products use successfully. The one concrete addition from research, recorded here as a hard rule:

- **Never gate a capability that a free/open-source user already had.** HashiCorp, Redis, and MongoDB all triggered forks and, in HashiCorp's case, did not survive as an independent company, after regating previously-free functionality. Whatever the free/paid boundary ends up being (Phase Monetization has not yet been scoped in detail -- deliberately deferred, matching the priority order below), it must be decided and communicated once, before any module ships, never tightened retroactively.

## 39.7 Explicit non-goals for this pass

- **Android / Field Operations Mobile is postponed**, not cancelled. Reason, recorded precisely per the owner's own framing: offline strategy, device ownership, printer/hardware abstraction, sync-conflict UX, and field-worker permission model are all still-open product decisions that mobile work would otherwise have to guess at. The existing standalone design document (`Dakhl_FieldOperations_Mobile_Hardware_Spec.md`) remains valid reference material for whenever this phase is picked back up -- it is not being discarded, just sequenced after Phases R/U/AI/Integration architecture settle enough to inform those open decisions.
- **No generic AI chatbot.** Research was unambiguous and unanimous across every product studied: a standalone chat panel is the weakest-value AI pattern in the entire market. Not building one.
- **No new large features** in this pass. This entire §39 was a research-and-documentation pass; the only code changes made alongside it were the two small, already-tracked Phase R items (R-01 pending, R-02 done) and the Release Readiness Audit fixes (7zip-bin, git hygiene) -- see the Decision Log's own entry for this pass for full detail.

## 39.8 Priority order (explicit)

1. **R-01** (first-login forced password change) -- small, closes the one real remaining pre-public-release blocker.
2. **Phase U** (User Simplicity Layer) -- the highest product-value work available right now on a backend that's already ready for it.
3. **Phase AI architecture design** (not implementation) -- do this once Phase U's patterns (what a "simple" surface looks like) are proven out on a couple of real screens, so the AI layer's own simple-mode surface follows the same proven pattern rather than inventing a third one.
4. **Phase Integration architecture design** (not implementation) -- can happen in parallel with AI architecture design since it depends on the existing sync infrastructure, not on Phase U/AI being done first.
5. **Phase Monetization detailed scoping** -- deliberately last; premature to gate specific capabilities before Phases U/AI/Integration exist to be gated.
6. **Android/Field Operations Mobile** -- resumes only after enough of Phases U/AI/Integration exist to make the open mobile-specific decisions (§39.7) with real product context instead of guesswork.

---

## 40. Product Vision Integration Pass (2026-09-25) — documentation only

Source: owner instruction file (Desktop\1.txt) + 4-axis parallel research. Full narrative: `Dakhl_PRODUCT_VISION_AND_STRATEGY_2026-09-25.md`. **No code, no schema change, no D-0XX text authored by the assistant.**

### 40.1 "Forgotten decisions" audit result
Both owner concerns were already recorded: inter-shop exchange/consignment = **D-074** (LOCKED as future architecture only; Guardrail #5), industry price intelligence = Decision Log "Industry Intelligence / Market Data" (LOCKED principle, unimplemented). They were not lost, only not linked into the roadmap. §40.3 links them.

### 40.2 Epic mapping (reconciles the file's colliding P13–P18 names; numbers are labels, not committed timelines)
| Working name | Meaning | Relation to existing work |
|---|---|---|
| Phase U | Simple Mode / zero-accounting UX | §39.2, unchanged, next after R-01 |
| Phase AI | Financial Copilot / "Business Brain" | §39.4, design only |
| Phase Integration | Integration Hub / Marketplace | §39.5, extends D-076–D-080 sync |
| Phase Migration (was "P13 Universal Migration") | Import platform | New; validate demand via interviews first (§40.5) |
| Phase Relationship (was "P13 Business Relationship Engine") | Party metadata over existing Customer | New; additive layer only |
| Phase Trade (was P16/P18) | D-074 build | **Gated: owner must explicitly open** |
| Phase Market (was P14) | Industry Intelligence providers | Gated by data-licensing; pilot gold/coin/FX via BYO key |
| Phase Monetization | Entitlements/licensing | §39.6, last |
| Android/Field Mobile | postponed | **Last, after all of the above** |

### 40.3 Constraints carried forward
- D-074 non-goals stand: no Trade/Deal/Consignment/OpenExchange/Position code and no Generic*/Universal* abstractions until the owner opens a Trade phase.
- "Business Event Engine replaces Invoice/Customer" is **not adopted**: conflicts with "preserve Subject→Document→Transaction spine". Any event layer must be additive.
- `Customer` already serves buy+sell and nets on one `subject_id`; Party = metadata over it (open question, owner decides).
- Decision numbering: D-091…D-098 are taken (roles/company). Next free = **D-099**. The file's proposed D-092..D-096 must not be used.

### 40.4 Research-driven design inputs (details in vision doc ch. 4–9)
Exchange as own entity with legs (not money-only offset JE); owner dimension separate from warehouse; quantity positions + price-fix event, ledger touched only at fix/settlement; provider adapters with explicit `unit`, `source_ts`, freshness states, circuit-breaker; clearing-account + unique (source, external_id, event_type) for integrations; migration schema triple `source_system/source_id/import_batch_id`, staging, opening-balance document type, reconciliation report.

### 40.5 Risks / caveats
Gold-weight and bazaar-practice details are **unverified** (marketing pages only) → validate with 3–5 real shop owners. Migration demand is **unvalidated** (advice says most firms skip history) → 5–10 interviews before connectors. Mobile price feeds have no legal source today. Competitor AI accuracy figures are vendor-reported.

### 40.6 Open questions for the owner
Listed in vision doc ch. 12 (open Trade phase? Party model? offset key? valuation rule? managed vs BYO feed? migration priority? D-099 authorship).
