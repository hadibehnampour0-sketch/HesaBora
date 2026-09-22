# Dakhl — Market, Open-Source & Product Differentiation Benchmark

## Executive conclusion

Dakhl does not need to win by becoming the software with the longest feature list. The strongest opportunity is to become the accounting system that **continuously proves the business is financially and tax-ready**.

The benchmark across Iranian products, major open-source ERP/accounting platforms, and current AI-enabled accounting products shows a consistent pattern:

- Iranian products are strong on localized accounting, sales/inventory/treasury modules, Moadian connectivity, support, and industry packages.
- ERPNext and Odoo are strong on accounting architecture, reconciliation, dimensions, taxes, assets, recurring workflows, reporting, and extensibility.
- Akaunting, Dolibarr and InvoicePlane are strong examples of simpler/open-source product packaging and modular breadth, but are less specialized for Iran.
- Xero, QuickBooks and Sage show the current direction of accounting software: bank feeds, automated reconciliation, document capture, cash-flow insight, and AI assistance.
- Iranian open-source repositories are much thinner than the global ecosystem. The strongest useful local code references located were Hesabix Core (archived in 2025), Moadian protocol examples, and Moadian/WooCommerce connector projects. These are evidence sources, not a reason to copy code.

The product whitespace for Dakhl is therefore not “another Iranian accounting package.” It is a **continuous compliance and financial control platform** that sits on top of a strong accounting core.

> Proposed category: **Tax-Ready Accounting & Business Control Platform for Iran**

Core promise:

> Enter a business event once. Dakhl keeps the books, payments, inventory, electronic invoices, tax positions, evidence, bank reconciliation, and filing data aligned throughout the year.

This is more defensible than competing on invoices, payroll, or dashboards alone.

---

## 1. Benchmark scope and evidence standard

This benchmark combines:

1. Dakhl's previously audited architecture and repository state.
2. Current public information from Iranian accounting vendors.
3. Current documentation and source repositories for ERPNext, Odoo, Akaunting, Dolibarr and InvoicePlane.
4. Current AI/accounting positioning from Xero, QuickBooks and Sage.
5. Iranian open-source GitHub projects and Moadian connectors.
6. Recent GitHub issues, to identify real-world pain points that remain even in mature products.

The comparison is intentionally **evidence-first**. A feature is not treated as present in a competitor merely because it is common in the market. Publicly confirmed product documentation is preferred; feature gaps are described as “not publicly evidenced” rather than assumed absent when evidence is incomplete.

The market review is broad, but it is not a legal certification or a complete census of every Iranian accounting vendor.

---

# 2. Iranian market: what established products already do well

## 2.1 Sepidar / Sepehr-style enterprise SMB stack

Sepidar's public product material shows a mature modular structure covering accounting, receiving/payments, inventory, sales, fixed assets, payroll, customer/sales management, production, distribution, contracting and a dedicated Moadian integration layer. Its commercial accounting package also advertises cheque operations, inventory reservation, import costing, electronic invoice and tax-related exports. Its service product explicitly advertises automatic bank-reconciliation statements and execution of legal reporting obligations.

That means Dakhl cannot differentiate merely by saying:

- “we have accounting”;
- “we have inventory”;
- “we manage cheques”;
- “we connect to Moadian”.

The market already has those messages.

Source: Sepidar official product pages.

---

## 2.2 Parmis

Parmis is particularly important because its public tax positioning is close to what Dakhl is considering. Parmis markets Moadian as an integrated subsystem that handles electronic invoices, sending, status tracking, errors, and reporting. Its broader product offering also includes mobile/tablet/POS/barcode/scale integrations, multiple-user controls, cheque/installment management, CRM, workflows, multi-branch structure and electronic commercial books.

The competitive lesson is that **“Moadian integration” alone is no longer a differentiator**. It is expected.

The whitespace is what happens after transmission:

- Was the tax data accepted?
- Does it reconcile with Dakhl's books?
- Is the VAT treatment valid?
- Are the supporting documents complete?
- Does the year-end return reconcile to the official data?
- What should the owner fix today?

Source: Parmis official product and taxpayer-system pages.

---

## 2.3 Mahak

Mahak publicly offers a Moadian service, analytics and other business integrations. The product strategy is service-oriented and modular, with paid/support-dependent infrastructure around some integrations.

The lesson is not a feature to copy. It is a commercial insight: Iranian accounting buyers care about **not being left alone with government integrations**.

Dakhl can turn this into a product experience instead of a support dependency:

> every failed tax action comes with a reason, affected document, exact correction step, and verification result.

---

## 2.4 Holoo

Holoo's ecosystem shows the strength of industry-specific packages, point-of-sale/hardware compatibility, and Moadian connectivity. Public material highlights software variants for different business types and a direct connection path to Moadian.

The lesson is that Iranian customers often do not want “generic accounting.” They want software that understands their workflow.

Dakhl should therefore provide **business templates and tax profiles**, not separate codebases for every industry.

Example:

```text
Business Profile: Retail Clothing
  ↓
Tax Profile
  ↓
Default invoice behavior
  ↓
Inventory behavior
  ↓
Common expense categories
  ↓
Moadian mappings
  ↓
Reports / dashboards
```

This gives verticalization without fragmenting the core.

---

## 2.5 Vira

Vira is an especially relevant contemporary Iranian competitor because it publicly positions itself as an integrated cloud business-management platform with accounting, inventory, CRM, treasury, payroll and Moadian on one shared core. It also advertises a smart assistant that answers questions from business data.

This shows that “all-in-one + cloud + AI assistant” is already entering the Iranian market.

Therefore Dakhl should not copy the message “we also have AI.”

Its AI should answer a much harder question:

> “Is my business financially and tax compliant, what is wrong, and what should I fix before the organization finds it?”

That is a much stronger use of AI than a chat box on top of accounting data.

---

# 3. Iranian open-source ecosystem: what is actually available

## 3.1 Hesabix Core

Hesabix Core is a Persian open-source accounting codebase whose repository describes it as the API base of the accounting software. It includes Persian UI, multi-currency support, financial reports, inventory, payroll, taxes and multi-user support. The repository was archived on July 11, 2025 and remains read-only on GitHub.

Architecturally useful observations:

- a Persian-native accounting project can reach substantial scope;
- an API-first core is a viable pattern;
- local open source accounting exists, but the ecosystem is smaller than ERPNext/Odoo;
- archived status makes it more useful as an evidence/reference system than as an active dependency.

Source: GitHub `morrning/hesabixCore`.

---

## 3.2 Moadian Laravel example

A small Laravel example was found showing how to use the `jooyeshgar/moadian` package, obtain a server nonce, retrieve server information, and handle API responses.

The useful lesson is architectural rather than code-level: Moadian should be isolated behind a dedicated adapter/service boundary so protocol changes do not leak into invoice/accounting logic.

Source: GitHub `BaseMax/moadian-api-laravel-example`.

---

## 3.3 WooCommerce Moadian connector

The WooCommerce Moadian connector is particularly instructive because it implements an explicit asynchronous invoice lifecycle:

```text
queued
  ↓
sending
  ↓
pending
  ↓
success / failed
```

It deliberately distinguishes an accepted asynchronous submission from final success, retries failed submissions, and supports inquiries of pending references. It also exposes extension hooks and has protocol-oriented tests.

This is a pattern Dakhl should adopt conceptually:

> **Never collapse “sent” and “accepted” into one status.**

The integration should have explicit transport and authority states.

Source: GitHub `ildrm/wc-moadian-connector`.

Important license note: the repository is GPL-2.0-or-later. It is an evidence source, not a drop-in code dependency for a product without a separate license review.

---

# 4. Global open-source benchmarks

## 4.1 ERPNext — the strongest architecture benchmark

ERPNext has one of the clearest separations between:

```text
Source document
      ↓
General Ledger
      ↓
Payment Ledger / allocation
```

Its accounting documentation explicitly distinguishes the source document, GL Entry and Payment Ledger Entry. Receivable/payable transactions are stored in a payment ledger so reconciliation can update allocation without rewriting the original bank movement.

The Payment Reconciliation tool supports:

- one payment → multiple invoices;
- multiple payments → one invoice;
- partial allocation;
- unallocated balances;
- reconciliation without creating another bank movement;
- later correction/unreconciliation.

ERPNext also supports payment terms, ageing, bank transaction imports, bank reconciliation, dimensions, tax templates, assets and a broad reporting stack.

The architectural lesson for Dakhl is extremely important:

> **Separate the financial event from how that event is allocated.**

That directly validates the Dakhl direction of introducing `PaymentAllocation` instead of overloading `Payment` or `Invoice`.

Sources: ERPNext accounting introduction; Payment Reconciliation documentation; Accounting Entries documentation; GitHub `frappe/erpnext`.

---

## 4.2 Odoo — strongest workflow and tax benchmark

Odoo's current accounting documentation shows a highly integrated architecture covering:

- double-entry accounting;
- multi-company and multi-currency;
- branches;
- taxes and tax mappings;
- payment terms and installment plans;
- electronic invoicing;
- invoice digitization with OCR/AI;
- payments and checks;
- bank synchronization and reconciliation;
- analytic accounting;
- budgets;
- assets;
- tax returns;
- audit trails;
- year-end closing.

Its tax model is especially instructive. Taxes have distribution rules and Tax Grids, and the tax return is generated from those tagged accounting lines. This is a stronger concept than simply storing a VAT percentage on an invoice.

Odoo also uses AI/OCR to digitize vendor bills and receipts into accounting records.

The Dakhl lesson is:

> Tax must be a **classification and evidence system**, not merely arithmetic.

Sources: Odoo Accounting and Invoicing documentation; Taxes documentation; Tax Return documentation; Analytic Accounting documentation; Invoice Digitization documentation.

---

## 4.3 Akaunting — strong simplicity and bank connection model

Akaunting focuses on simple SMB accounting and exposes clear concepts for:

- invoices and bills;
- payment splitting;
- recurring invoices;
- bank feeds;
- reconciliation;
- connecting bank transactions to invoices/bills;
- splitting a transaction for partial invoice payments.

Its simplicity is useful evidence for Dakhl's user experience: advanced accounting logic does not have to mean a complicated front-end.

Source: Akaunting documentation.

---

## 4.4 Dolibarr — integration breadth and embedded AI direction

Dolibarr is a broad open-source ERP/CRM with invoicing, payments, bank accounts, accounting, projects, tasks, HR, expenses, documents, barcodes and many integrations.

Its current source also exposes AI tools that can query invoices and register payments. This is a meaningful signal: AI is moving from “analysis-only” toward controlled business actions.

That is a future opportunity for Dakhl, but only with permission boundaries, audit trails and explicit confirmation for financial actions.

Source: GitHub `Dolibarr/dolibarr`.

---

## 4.5 InvoicePlane — useful benchmark for minimal invoicing

InvoicePlane is a self-hosted open-source project focused on invoices, clients, payments, customization and reporting. Its GitHub project is useful as a counter-example: it demonstrates how far a narrow invoicing product can go without being a full accounting/ERP platform.

The lesson is to avoid turning Dakhl into a bloated invoice application. The real value is the accounting, reconciliation, tax and evidence layer behind the invoice.

Source: GitHub `InvoicePlane/InvoicePlane`.

---

# 5. AI/accounting direction in 2026

The commercial products now show a clear pattern.

## Xero

Xero's current JAX system uses four reconciliation modes:

- Rule
- Match
- Memory
- Prediction

It automatically reconciles when confidence is high, suggests matches when confidence is lower, and gives the user visibility and control. It also attaches receipts/documents directly during reconciliation and offers financial health scorecards and cash-flow forecasting.

This is important because Xero is not selling “AI chat.” It is selling **AI embedded in a real accounting workflow**.

Source: Xero bank reconciliation documentation.

## QuickBooks

QuickBooks Online's recent product updates emphasize AI-powered bank feeds and PDF bank-statement extraction, with the goal of reducing manual transaction handling and simplifying bank connection support.

Again, AI is being used for data movement and cleanup, not only natural-language conversation.

Source: QuickBooks Online 2026 product update and bank feeds documentation.

## Sage

Sage promotes AI accounting, bank feeds that import and automatically match transactions, automatic capture/categorization, and broader accounting + people/ERP products. Its AutoEntry product specifically captures and categorizes invoices, receipts and bank/card statements.

Source: Sage Accounting and product pages.

---

# 6. What current GitHub issues reveal: mature products still have painful seams

This is one of the most useful parts of the benchmark.

## ERPNext issues

Recent ERPNext issues show users still struggle with:

- bank reconciliation behavior;
- matching at the payment level;
- bulk reconciliation bugs;
- reconciliation after tax deductions;
- missing reconciliation history;
- small write-off/difference handling;
- multi-ledger bank structures.

These are not reasons to avoid ERPNext. They show that **reconciliation is hard even for a mature product**.

Sources: GitHub ERPNext issues #53531, #54823, #55913, #56971, #58323 and #51159.

### Product opportunity for Dakhl

Do not make Dakhl's reconciliation screen merely a table with “match” buttons.

Make it an **explainable decision engine**:

```text
Bank line
   ↓
Candidate matches
   ↓
Why this is a candidate
   ↓
Confidence
   ↓
Tax impact
   ↓
Accounting impact
   ↓
User confirms / rejects
   ↓
Audit record
```

## Odoo issue signal

A current Odoo 19 issue reports a case where unreconciling one bank-statement reconciliation can incorrectly affect other reconciliations associated with the same invoice/journal item.

The lesson is broader than the bug itself:

> Reconciliation needs a durable, first-class history model.

Dakhl should build that history from day one instead of adding it after the feature is live.

## Dolibarr issue signal

A long-standing Dolibarr issue shows the user pain caused when a payment reaches the ledger before reconciliation and then becomes hard to correct.

The lesson is that Dakhl should define an explicit lifecycle:

```text
Captured
→ Classified
→ Posted
→ Reconciled
→ Locked
```

rather than treating “posted” and “verified” as the same thing.

---

# 7. The biggest competitive gap: nobody owns the entire evidence chain

Most accounting products answer:

> “What happened financially?”

Tax modules answer:

> “What did we send to the government?”

Bank reconciliation answers:

> “Does the bank agree?”

Document management answers:

> “Where is the file?”

Dakhl can combine all four:

```text
                    BUSINESS EVENT
                         │
           ┌─────────────┼─────────────┐
           ▼             ▼             ▼
       Accounting     Tax Data      Evidence
           │             │             │
           └─────────────┼─────────────┘
                         ▼
                  Reconciliation Graph
                         │
       ┌─────────────────┼─────────────────┐
       ▼                 ▼                 ▼
      Bank             Moadian            Filing
       │                 │                 │
       └─────────────────┼─────────────────┘
                         ▼
                   Audit-Ready File
```

This graph becomes the foundation for a completely different product experience.

---

# 8. The Dakhl differentiator I recommend

## Category: Continuous Tax-Ready Accounting

Not:

> “Accounting software with a tax module.”

But:

> “A financial system that keeps your business continuously ready for tax filing and audit.”

### The system would maintain five simultaneous truths

```text
1. BOOKS
   What the accounting ledger says.

2. TAX
   What the tax rules imply.

3. GOVERNMENT
   What the taxpayer system accepted/rejected.

4. CASH
   What bank/cash evidence shows.

5. EVIDENCE
   What supporting documents prove.
```

Every transaction receives a status across all five.

Example:

```text
Sale #14052

Books:          ✅ Posted
Moadian:        ✅ Accepted
VAT:            ✅ Valid
Bank:           ✅ Received
Evidence:       ✅ Complete
Tax risk:       🟢 Low
```

Or:

```text
Purchase #8821

Books:          ✅ Posted
Moadian:        ⚠ Pending
VAT:            🔴 Not currently eligible
Bank:           ✅ Paid
Evidence:       🟡 Missing delivery proof
Tax risk:       🔴 High

Fix:
Confirm electronic invoice + attach delivery document.
```

That is materially different from conventional accounting UI.

---

# 9. Feature ideas that create real differentiation

## 9.1 Tax Health Center

One page that answers:

- What is my tax position today?
- What is missing?
- Which documents are risky?
- What changed since last period?
- What will likely appear in the next return?
- What is the estimated liability?
- What can I legally optimize before year-end?

This should be the tax equivalent of a modern security dashboard.

---

## 9.2 Tax Risk Engine

Rules detect:

- invoice rejected or pending in Moadian;
- suspicious VAT status;
- missing evidence;
- bank/accounting mismatch;
- abnormal sales or expense patterns;
- unusual credit notes/returns;
- unallocated payments;
- overdue receivables;
- missing customer/vendor tax information;
- transactions whose tax classification conflicts with the business profile.

Each risk should explain:

```text
What happened?
Why does it matter?
Which rule triggered?
What source data supports it?
How can I fix it?
```

---

## 9.3 Tax Simulator

The user should be able to ask:

> “اگر این هزینه را امسال ثبت کنم، اثرش روی سود و مالیات چقدر است؟”

or:

> “اگر این فاکتور را برگشت بزنم، VAT و مالیات و مانده مشتری چه تغییری می‌کند؟”

The system runs a non-posting simulation and shows:

```text
Current position
      ↓
Scenario
      ↓
Accounting impact
      ↓
VAT impact
      ↓
Income-tax impact
      ↓
Cash impact
      ↓
Risk impact
```

This must be explicitly marked as a simulation, not a legal ruling.

---

## 9.4 Continuous Close

Instead of a year-end accounting sprint:

```text
Daily / Weekly
   ↓
Reconcile
   ↓
Fix risks
   ↓
Close month
   ↓
Tax-ready score
```

At year end the system should say:

> “There are 3 unresolved items left.”

not:

> “Bring everything to the accountant and hope.”

---

## 9.5 Audit Pack Generator

A single action creates a structured package:

```text
Tax Year 1405
│
├── Executive Summary
├── Trial Balance
├── General Ledger
├── Sales Register
├── Purchase Register
├── VAT Summary
├── Customer Statements
├── Supplier Statements
├── Bank Reconciliation
├── Cheque Register
├── Payroll Summary
├── Fixed Assets
├── Tax Adjustments
├── Moadian Status Report
└── Supporting Evidence Index
```

Every number should link to source records.

This turns “accountant preparation” into “review and exception handling.”

---

# 10. Make reconciliation explainable, not merely automatic

The strongest idea borrowed from Xero should be adapted rather than copied.

Dakhl's matching engine can calculate candidate scores using:

```text
Amount similarity
Date proximity
Party similarity
Reference similarity
Invoice number similarity
Bank description similarity
Historical match behavior
Tax compatibility
Account compatibility
```

Example:

```text
Bank transaction: 82,500,000

Candidate #1  Invoice 1452   98%
Candidate #2  Invoice 1447   71%
Candidate #3  Customer credit 54%

Reason for #1:
+ exact amount
+ 1-day date distance
+ same customer
+ reference contains invoice number
+ same expected settlement account
```

The user can approve or reject.

Over time the system learns organization-specific patterns.

---

# 11. Build the missing Payment Allocation layer

The benchmark strongly supports the following abstraction:

```text
Payment
   │
   ├── PaymentAllocation → Invoice A
   ├── PaymentAllocation → Invoice B
   └── Unallocated Credit
```

This unlocks:

- one payment → many invoices;
- many payments → one invoice;
- partial allocation;
- customer credit;
- refunds;
- correction without a duplicate bank movement;
- robust reconciliation;
- cheque settlement provenance.

This is one of the highest-value architectural improvements for Dakhl.

---

# 12. Build Check Settlement on the same conceptual backbone

The Dakhl cheque opportunity should become:

```text
Cheque 10M
   │
   ├── Settlement 2M ← Payment A
   ├── Settlement 3M ← Payment B
   └── Settlement 5M ← Payment C

Total settled = 10M
Status = Fully Settled
Action = Return cheque
```

The critical rule:

```text
sum(settlements) <= cheque.amount
```

And a fully settled guarantee cheque should not create a fake second receipt when returned.

This turns the cheque subsystem from a generic bank instrument module into an Iranian business workflow differentiator.

---

# 13. API should become the integration fabric

The existing Dakhl token-management screen is evidence that API infrastructure already exists.

The benchmark suggests turning that into a formal API platform:

```text
/api/v1/

Customers
Products
Invoices
Payments
Cheques
Bank Transactions
Tax Status
Reports
Evidence
Reconciliation
```

Security/quality requirements:

- scoped/revocable tokens;
- explicit company context;
- per-token permissions;
- idempotency keys for financial POSTs;
- pagination/filtering;
- consistent errors;
- audit trail;
- webhooks/events;
- OpenAPI documentation.

The API must call the same service/domain layer as the UI.

---

# 14. AI should be allowed to recommend and explain before it is allowed to post

A safe maturity model:

### Level 1 — Explain

```text
“چرا مالیات این ماه بیشتر شده؟”
```

### Level 2 — Detect

```text
“۳ فاکتور مشکوک پیدا کردم.”
```

### Level 3 — Recommend

```text
“این ۳ مورد را بررسی کن؛ این اصلاحات ممکن است وضعیت را درست کند.”
```

### Level 4 — Draft

```text
“من اصلاح پیشنهادی را آماده کردم.”
```

### Level 5 — Execute with confirmation

```text
“این اصلاح مالی ۴۰ میلیون تومان اثر دارد.
تأیید می‌کنی ثبت شود؟”
```

Direct autonomous accounting posting should not be the default.

---

# 15. What NOT to copy from competitors

## Do not copy feature count

A 600-feature product does not automatically create more value.

## Do not create separate logic per industry

Use profiles/configuration/rules instead of code forks.

## Do not turn AI into a generic chat box

The AI must operate on actual accounting/tax evidence and explain where its conclusion came from.

## Do not let reconciliation mutate the underlying money movement

The benchmark repeatedly shows how complex this becomes. Allocation and reconciliation should be separate from the original financial posting.

## Do not couple the tax engine directly to invoice code

Tax rules should be versioned, date-effective and independently testable.

## Do not delete financial history

Correction should use reversal/amendment/versioned workflows.

---

# 16. Proposed future Dakhl architecture

```text
                             Dakhl
                                │
        ┌───────────────────────┼────────────────────────┐
        │                       │                        │
        ▼                       ▼                        ▼
   Business Layer        Accounting Core          Evidence Layer
        │                       │                        │
  Sales / Purchases       Documents                  Attachments
  Inventory               Transactions               Contracts
  Customers               Subjects                   Receipts
  Payroll                 Journal                     Tax proofs
  Cheques                                             Bank files
        │                       │                        │
        └───────────────────────┼────────────────────────┘
                                ▼
                         Control Layer
                                │
                ┌───────────────┼────────────────┐
                ▼               ▼                ▼
          Reconciliation    Tax Engine      Risk Engine
                │               │                │
                ▼               ▼                ▼
              Bank          Moadian          Anomaly/Risk
                │               │                │
                └───────────────┼────────────────┘
                                ▼
                       Decision / Filing Layer
                                │
                 ┌──────────────┼──────────────┐
                 ▼              ▼              ▼
             Dashboards      Tax Return      Audit Pack
                 │              │              │
                 └──────────────┼──────────────┘
                                ▼
                              API
                                │
           ┌────────────────────┼────────────────────┐
           ▼                    ▼                    ▼
          POS                E-commerce            Mobile
```

---

# 17. Recommended competitive roadmap

## P0 — Financial integrity

Before market differentiation:

- finish Payment Allocation design;
- fix cheque settlement semantics;
- fix bounced cheque reversals;
- stop duplicate accounting postings;
- protect posted documents;
- add reconciliation history;
- harden money arithmetic;
- keep a clean audit trail.

## P1 — Continuous reconciliation

- bank statement import;
- Payment Allocation;
- bank matching;
- automatic candidate scoring;
- customer/vendor allocation;
- unallocated credit;
- reconciliation history;
- AR/AP ageing;
- drill-down.

## P2 — Tax Compliance Foundation

- taxpayer profile;
- tax rule engine;
- Moadian state machine;
- VAT engine;
- evidence graph;
- tax risk engine;
- tax-health dashboard.

## P3 — Filing and audit readiness

- performance-tax dataset;
- VAT return builder;
- commercial-electronic-books support;
- reconciliation to official data;
- audit pack;
- year-end close checklist.

## P4 — AI control layer

- explainable finance assistant;
- tax-risk assistant;
- anomaly detection;
- tax simulator;
- recommendation engine;
- controlled financial actions with confirmation.

## P5 — Ecosystem

- versioned API;
- webhooks;
- POS integrations;
- e-commerce;
- mobile;
- external accountant collaboration;
- external evidence collection.

---

# 18. The moat

The durable moat is not:

```text
Invoices + CRM + Payroll + Inventory
```

Every serious ERP can reach that list.

The moat should be:

```text
Tax Rules
    +
Accounting Truth
    +
Official Tax-System Truth
    +
Bank Truth
    +
Evidence Truth
    +
Continuous Reconciliation
    +
Explainable AI
```

Once a company has used Dakhl for a year, the system should know:

- every invoice;
- every payment;
- every cheque;
- every customer balance;
- every tax status;
- every bank match;
- every supporting document;
- every unresolved discrepancy;
- every historical tax rule used.

That creates switching cost based on **institutional memory and compliance history**, not just database size.

---

# 19. The most differentiated UX concept

The home dashboard should evolve beyond sales and profit cards.

## Dakhl Control Center

```text
┌──────────────────────────────────────────────┐
│ Financial Health                  92% 🟢      │
├──────────────────────────────────────────────┤
│ Tax Readiness                     88% 🟢      │
│ Bank Reconciliation               94% 🟢      │
│ Moadian Sync                      97% 🟢      │
│ Evidence Completeness             81% 🟡      │
│ AR Collection                     73% 🟡      │
└──────────────────────────────────────────────┘

Needs attention today

🔴 3 tax mismatches
🟠 7 unallocated payments
🟠 2 bank transactions without evidence
🟡 1 overdue cheque
🟢 42 invoices fully reconciled
```

The product is no longer asking the owner to “navigate accounting screens.”

It is telling them:

> **“Your business is in this state, and here are the next three actions.”**

That is the UX direction I would pursue.

---

# 20. Final strategic recommendation

Dakhl should not attempt to beat Sepidar, Parmis or Vira at their own game by copying every ERP module. It should also not attempt to become a smaller Odoo.

The winning strategy is:

### Keep the accounting core.

It is already a real double-entry foundation.

### Add the missing control layers.

Payment Allocation, Reconciliation, Cheque Settlement and Evidence.

### Make Iran-specific compliance a first-class domain.

Moadian, VAT, performance tax, commercial books, tax rules and evidence should live in one coherent system.

### Put AI where it creates measurable operational value.

Reconcile, detect risk, explain, simulate, recommend and only then execute with confirmation.

### Turn year-end accounting into continuous readiness.

The user should reach the end of the fiscal year with a nearly finished tax file instead of a pile of documents waiting for an accountant.

---

# 21. One-sentence positioning

> **Dakhl is not just where a business records its money; it is where the business keeps its financial and tax reality synchronized, explainable and ready.**

---

# Sources

1. ERPNext — Accounting Introduction: https://docs.frappe.io/erpnext/accounting-introduction
2. ERPNext — Payment Reconciliation: https://docs.frappe.io/erpnext/payment-reconciliation
3. ERPNext — Accounting Entries: https://docs.frappe.io/erpnext/accounting-entries
4. ERPNext GitHub accounts README: https://github.com/frappe/erpnext/blob/develop/erpnext/accounts/README.md
5. ERPNext GitHub issue #53531 — multi-ledger bank reconciliation: https://github.com/frappe/erpnext/issues/53531
6. ERPNext GitHub issue #54823 — payment-level bank reconciliation: https://github.com/frappe/erpnext/issues/54823
7. ERPNext GitHub issue #55913 — bulk reconciliation: https://github.com/frappe/erpnext/issues/55913
8. ERPNext GitHub issue #56971 — reconciliation history: https://github.com/frappe/erpnext/issues/56971
9. ERPNext GitHub issue #58323 — tax deduction/reconciliation: https://github.com/frappe/erpnext/issues/58323
10. ERPNext GitHub issue #51159 — reconciliation differences/write-offs: https://github.com/frappe/erpnext/issues/51159
11. Odoo 19 — Accounting and Invoicing: https://www.odoo.com/documentation/19.0/applications/finance/accounting.html
12. Odoo 19 — Taxes: https://www.odoo.com/documentation/19.0/applications/finance/accounting/taxes.html
13. Odoo 19 — Tax Return: https://www.odoo.com/documentation/19.0/applications/finance/accounting/reporting/tax_returns.html
14. Odoo 19 — Analytic Accounting: https://www.odoo.com/documentation/19.0/applications/finance/accounting/reporting/analytic_accounting.html
15. Odoo 19 — Invoice Digitization: https://www.odoo.com/documentation/19.0/applications/finance/accounting/vendor_bills/invoice_digitization.html
16. Odoo GitHub: https://github.com/odoo/odoo
17. Odoo GitHub issue #279278 — unreconciliation interaction: https://github.com/odoo/odoo/issues/279278
18. Akaunting — Banking, Feeds and Reconciliations: https://akaunting.com/hc/docs/banking-feeds-reconciliations/
19. Akaunting — Reconciling Transactions: https://akaunting.com/hc/docs/banking-feeds-reconciliations/reconciling-transactions/
20. Akaunting — Transactions / connecting payments: https://akaunting.com/hc/docs/banking-feeds-reconciliations/transactions/
21. Akaunting — Recurring invoices: https://akaunting.com/hc/docs/invoices-and-estimates/creating-recurring-invoices/
22. Dolibarr GitHub: https://github.com/Dolibarr/dolibarr
23. Dolibarr AI invoice tool: https://github.com/Dolibarr/dolibarr/blob/develop/htdocs/ai/tools/invoices.class.php
24. Dolibarr bank language/reconciliation: https://github.com/Dolibarr/dolibarr/blob/develop/htdocs/langs/en_US/banks.lang
25. InvoicePlane GitHub: https://github.com/InvoicePlane/InvoicePlane
26. InvoicePlane organization GitHub: https://github.com/invoiceplane
27. Xero — Bank Reconciliation / JAX: https://www.xero.com/us/accounting-software/reconcile-bank-transactions/
28. Xero — Bank Feeds: https://www.xero.com/us/accounting-software/connect-your-bank/
29. Xero — Mobile / JAX: https://www.xero.com/us/accounting-software/xero-accounting-mobile-app/
30. QuickBooks — 2026 product updates: https://quickbooks.intuit.com/learn-support/en-us/help-article/intuit-subscriptions/new-quickbooks-online/L66q5XLC5_US_en_US
31. QuickBooks — Bank Feeds: https://quickbooks.intuit.com/global/bank-feeds/
32. Sage — Bank feeds and connections: https://www.sage.com/en-gb/sage-business-cloud/sage-accounting/features/connect-my-bank/
33. Sage — Product portfolio: https://www.sage.com/en-gb/products/
34. Sepidar — official product site: https://www.sepidarsystem.com/
35. Sepidar — Commercial accounting: https://www.sepidarsystem.com/products/sepidar/commercial-accounting-software/
36. Sepidar — Moadian: https://www.sepidarsystem.com/products/sepidar/taxpayer-system/
37. Parmis — official site: https://www.parmisit.com/
38. Parmis — Moadian: https://www.parmisit.com/taxpayer-system/
39. Mahak — accounting services / Moadian: https://www.mahaksoft.com/accounting-services/
40. Holoo ecosystem / Moadian evidence: https://www.holoocenter.com/%D8%A7%D8%AA%D8%B5%D8%A7%D9%84-%D9%87%D9%84%D9%88-%D8%A8%D9%87-%D8%B3%D8%A7%D9%85%D8%A7%D9%86%D9%87-%D9%85%D9%88%D8%AF%DB%8C%D8%A7%D9%86/
41. Vira — official product site: https://virasoftware.com/
42. Vira — accounting module: https://virasoftware.com/accounting-software
43. Vira — tax module: https://virasoftware.com/modules/tax
44. Dakhl upstream / FreeAmir GitHub: https://github.com/Jooyeshgar/FreeAmir
45. Hesabix Core GitHub (archived): https://github.com/morrning/hesabixCore
46. BaseMax Moadian Laravel example: https://github.com/BaseMax/moadian-api-laravel-example
47. WooCommerce Moadian connector: https://github.com/ildrm/wc-moadian-connector

---

## Product decision derived from the benchmark

The benchmark supports a strategic change in Dakhl's roadmap:

**Do not optimize the roadmap for “more modules.” Optimize it for “more trusted financial truth.”**

The highest-value sequence is:

```text
Accounting Integrity
        ↓
Payment Allocation
        ↓
Bank + Payment Reconciliation
        ↓
Cheque Settlement
        ↓
Tax Compliance Engine
        ↓
Evidence Graph
        ↓
Continuous Tax Readiness
        ↓
Explainable AI
        ↓
API Ecosystem
```

That sequence creates a product category which is harder to compare directly against traditional Iranian accounting software.
