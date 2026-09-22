# Dakhl / دخل — Decision Log
## Consolidated through 2026-09-12

This is the durable record of product and architecture decisions made during the current planning cycle. It exists so important decisions are not lost when an agent/session resets.

---

## Product Identity

- Product brand is **دخل / Dakhl**.
- FreeAmir/امیر is upstream/base heritage only, not the product brand.

## Accounting Core

- Do not rewrite the existing accounting engine casually.
- Preserve the current `Subject -> Document -> Transaction` accounting spine.
- Transactions are the canonical source for balances; legacy customer balance is not the source of truth.
- Posted financial records must become effectively immutable; corrections should use controlled reversal/adjustment flows.
- Financial calculations should use exact/decimal-safe arithmetic at money boundaries.

## User Experience

- Dakhl must be usable by people with little or no accounting knowledge.
- Users interact in business language, while accounting complexity is handled behind the scenes.
- Simple Mode and Advanced/Accountant Mode are two experiences over the same business logic and data.
- Principle: **Hide complexity, not capability.**
- High-frequency flows should use wizards, smart defaults, progressive disclosure, contextual help and error prevention.
- Expert users must still be able to drill down into journal/transaction/tax/audit details.

## Tax

- Tax compliance must be continuous rather than a year-end manual project.
- Dakhl should evolve toward a Tax Compliance / Tax Operating System for Iran.
- Tax rules, rates, thresholds and exemptions must be versioned/effective-dated data.
- Accounting treatment, tax treatment, Moadian/tax-system status and payment status are separate concepts that must be reconciled.
- Costs can be valid accounting expenses while having a different tax deductibility status.
- VAT eligibility must be modeled explicitly.
- The goal is an audit-ready/defensible file, not a promise that all users will never face a lawful tax audit or professional review.

## Payments / Reconciliation

- Payment is the financial movement; allocation is how the payment is applied to invoices/obligations.
- Add first-class `PaymentAllocation` capability.
- One payment may cover multiple invoices.
- Multiple payments may cover one invoice.
- Unallocated customer credit must be supported.
- Bank statement events must be separated from book/accounting events and reconciled rather than double-posted.
- First bank reconciliation version should favor CSV/Excel import and manual matching before live bank APIs.

## Cheques

- Guarantee cheques should remain off-balance until a legitimate financial event occurs.
- Current cheque flow has correctness risks around duplicate posting, execute, bounce and return.
- Introduce `CheckSettlement` as the target abstraction for installment settlement.
- Settlement total must never exceed cheque amount.
- Real payments should be linkable to settlements.
- Fully settled should be explicit/derived.
- Return should not create a fake second receipt.

## Offline + Online

- Offline and Online are now mandatory architectural principles.
- Dakhl is one product with two runtimes.
- Offline: NativePHP/Electron + local SQLite.
- Online: browser/web + server database.
- They share the same Domain/Service Layer.
- Offline changes require outbox/sync semantics.
- Sync is business-operation/command aware, not naive row replication.
- Use idempotency, retries, cursors, conflict policies, auditability and reconnect reconciliation.
- Naive last-write-wins is not acceptable for financial events.
- Posted financial documents are immutable; sensitive approvals may remain server-authoritative.

## API

- The visible API section is currently an API token management layer using Sanctum, not a second accounting system.
- API and future AI must use the same Domain/Service Layer as Web/Desktop.
- Future API should be versioned (`/api/v1/...`) and include revocable/scoped tokens, explicit company context, validation, rate limiting, pagination, idempotency, structured errors, audit trail, webhooks and OpenAPI.
- A dedicated endpoint audit is required before calling the API production-ready.

## Industry Intelligence / Market Data

- Dakhl should become a daily business cockpit with industry-specific tools.
- Introduce `Industry Profile` to activate relevant widgets/calculators/intelligence.
- Market data must be isolated behind a Provider/Adapter abstraction.
- Support both:
  - Managed Provider: ready-to-use Dakhl connection.
  - Bring Your Own API: user-provided endpoint/key/provider.
- Credentials must be protected/encrypted and never leaked into normal logs/UI.
- Custom providers need connection test + symbol/instrument mapping.
- Use normalized `MarketQuote` data internally.
- Support primary/fallback providers and offline cache with visible freshness timestamps.
- Market data may suggest price/strategy but must never silently rewrite historical/posted transactions.
- Store timestamped price snapshots/provenance where a market price materially informs a transaction.

## AI / Copilot

- AI is not a parallel accounting system and not merely a chat widget.
- AI must act through the same services, permissions and audit trail as human users.
- AI should first explain, detect, recommend and assist; consequential financial actions require appropriate confirmation.
- Future capabilities include natural-language transactions, document extraction, reconciliation assistance, tax-risk explanations, forecasting and anomaly detection.
- AI recommendations should expose reason/evidence/confidence and be auditable.

## Product Differentiation Strategy

The product should differentiate through the combination:

`Accounting Truth + Tax Compliance + Reconciliation + Evidence + Industry Intelligence + Simple UX + AI`

The goal is not feature count. The goal is coherence and trust.

## Market / Open-Source Inspirations

Keep these as inspiration only, subject to licensing/legal review:

- Vyapar — business-first simplicity
- TallyPrime — decision-oriented business dashboard
- Wave — contextual guidance and simpler workflows
- Odoo — onboarding/localization and broader ERP patterns
- Zoho Books — action-oriented AI
- Xero — explainable reconciliation assistance
- OpenBooks / Hisaabo — API/AI/UI on a common service layer
- TaxHacker / Acta — document extraction and review
- Actual Budget — local-first philosophy
- Tickabox — Laravel/NativePHP + outbox/sync ideas
- OpenBB / OpenBull — provider abstraction/adapters

## Current Release Bug

- Packaged Windows build opens Login correctly.
- Wrong username/password can currently trigger HTTP 500.
- Correct behavior is a normal authentication error.
- Do not require PHP/MariaDB on end-user machine to fix this.

## Agent/Cloud Workflow Rule

- On reset, agents must first read the authoritative roadmap + decision log + repository.
- Read-only audits must not modify code.
- Coding must be checkpointed and scoped.
- Do not run a giant autonomous task spanning unrelated modules.
- Protected core changes require focused tests and explicit acceptance criteria.

---

# Decision Chain

The current intended architecture is:

```text
User / Web / Desktop / API / AI
            |
            v
     Common Domain Layer
            |
   +--------+---------+
   |        |         |
Accounting  Tax   Reconciliation
   |        |         |
   +--------+---------+
            |
      Evidence / Audit
            |
       Offline/Online
            |
         Sync Layer
```

The long-term user experience is:

```text
User says what happened
        |
        v
Dakhl understands the business event
        |
        v
Accounting + Tax + Evidence + Reconciliation
        |
        v
Human-readable result + warnings + readiness
```

---

# Next Gate

Before a major new product idea is implemented:

1. Complete P0.
2. Establish P1 accounting/reconciliation foundation.
3. Validate offline/online architecture against the real repository.
4. Validate the tax domain model before building large tax UI.
5. Keep all new features on the shared Domain/Service Layer.

