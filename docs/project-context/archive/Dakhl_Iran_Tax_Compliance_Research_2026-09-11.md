# Dakhl — Iran Tax Compliance Research

## Executive conclusion

Dakhl can realistically be evolved from an accounting application into a **tax-compliance-first accounting platform** for Iranian SMBs. The strongest legal path is not to promise that every taxpayer will never face a tax audit; it is to make the taxpayer's books, e-invoices, VAT positions, payments, bank evidence, payroll/other tax data, and filing outputs continuously consistent with the official tax data model.

Iran's current framework already points in this direction. Article 19 of the Store Terminals and Taxpayer System law provides for performance-tax returns to be prepared from information in the taxpayer system and, where the submitted return matches the organization's data, accepted without ordinary examination; a limited random examination power remains. Article 20 also provides an exemption from presenting statutory books/documents for eligible taxpayers using approved accounting software and complying with the law. These provisions support a product strategy centered on **clean, traceable, machine-readable tax records** rather than manual year-end reconstruction.

The product target should therefore be:

> **“Enter each business event once; Dakhl keeps accounting truth, taxpayer-system truth, VAT truth, evidence, and filing data aligned all year.”**

It should be positioned as automation and compliance infrastructure, not as a legal guarantee that a particular tax assessment will never be challenged.

---

## 1. Current legal architecture that matters to Dakhl

### 1.1 Direct income tax

For legal entities, Article 105 generally taxes taxable income at 25%, subject to statutory exemptions and special-rate rules. For individual business taxpayers, Article 131 uses progressive rates. The latest consolidated legal text located for the current framework reflects 15% up to 4 billion IRR of annual taxable income, 20% on the next 4 billion IRR, and 25% above 8 billion IRR, with a separate statutory reduction mechanism tied to growth in declared taxable income and payment/filing conditions. The software should not hard-code these numbers as permanent constants; it should store annual/effective-date tax rules and preserve historical calculations.

Key point for Dakhl: **taxable income is not sales**. The system must distinguish revenue, cost of goods sold, allowable business expenses, depreciation, tax exemptions, tax credits/adjustments, and non-deductible expenses.

### 1.2 Allowable expenses and depreciation

Article 147/148 and related rules define when expenses are tax-deductible; Article 149 covers depreciable assets. Therefore a tax calculation engine cannot simply do:

`Sales - all expenses = taxable profit`.

It needs tax classification at the transaction/expense level, including evidence status and deductibility status. Depreciation should be a dedicated tax layer linked to an asset register and its applicable tax-depreciation rule.

### 1.3 VAT

Under the permanent VAT framework, tax is associated with the invoice/event rather than only when cash is collected. Article 4 provides for payment of output VAT after deduction of input tax credit, and the law contains specific rules around cash/credit transactions. The general VAT rate has been increased to a **10% total rate in 1405** by the applicable annual budget provision, but Dakhl must not assume that every good/service is taxable at 10%; exemptions and special treatment matter.

The software therefore needs line-level tax classification such as:

- taxable;
- exempt;
- zero-rated / special treatment where applicable;
- outside scope where legally relevant;
- tax base;
- VAT rate;
- output VAT;
- input VAT eligibility;
- effective date/source of rule.

### 1.4 Taxpayer System / electronic invoices

The Taxpayer System is the official exchange channel between taxpayers and the tax authority. The current legal framework treats the taxpayer work-profile as the place where information is exchanged. The 1404/95 regulation also confirms that electronic invoices have a unique tax identifier and that the taxpayer system is the final authority for registration, issuance and inquiry of electronic invoices.

The current production version of Dakhl should treat electronic-invoice state as a first-class business state, not a PDF-export feature.

### 1.5 Performance tax return and the "prefilled" direction

Article 19 is central to the product strategy. The law requires the tax authority to prepare/validate performance-tax information using taxpayer-system data for compliant taxpayers, and provides for acceptance without ordinary examination when the filed information matches the authority's data. The law also allows the taxpayer to file the performance return through the taxpayer system and requires sales/purchase information to flow into the performance return; the taxpayer still has to supply other required fields such as payroll, rent and depreciation where applicable.

A later 1405 instruction for performance 1404 also confirms a current move toward system-generated/prefilled returns and low-risk identification for compliant taxpayers.

This means Dakhl should not stop at "export an Excel file." It should maintain the exact structured values that will be needed to reconcile against the prefilled return.

### 1.6 Books and records

The latest located Article 95 regulation (21/08/1404) distinguishes paper and electronic books and explicitly defines electronic books as data registered through the commercial-electronic-books system. For taxpayers keeping electronic books, records still need to be maintained and documents may still be requested in audits under the applicable rules.

Therefore Dakhl should have two concepts that are often confused:

1. **Bookkeeping / evidence retention** — always a core product capability.
2. **Legal exemption from presenting books/documents under Article 20** — a conditional legal benefit for eligible taxpayers, not a blanket license to delete evidence.

### 1.7 Article 20 — the legal opening for the product

Article 20 of the Store Terminals and Taxpayer System law states that taxpayers who comply with all statutory obligations and use accounting software approved by the governing taxpayer-system committee can be exempted from presenting books or documents under the direct-tax and VAT laws, within the applicable implementing framework.

This is the closest legal foundation for the product objective of reducing year-end document handoff.

However, two cautions are critical:

- It is conditional on legal compliance and use of qualifying software.
- The same framework retains limited audit/verification powers; Article 19 allows random examination of a limited percentage of compliant taxpayers.

An earlier Article 20 instruction (29716, 1399) contained extra conditions, and parts of that instruction were annulled by the Administrative Justice Court in 1401. Dakhl must therefore avoid baking historical/invalid extra conditions into the product and must re-check the currently operative implementing text whenever the product seeks an official approval path.

### 1.8 Penalties / risk prevention

The tax system is sufficiently punitive that prevention must be a product feature. Current Article 22 rules include material penalties for failure to issue electronic invoices, failure to join/use required infrastructure, failure to declare business bank accounts, and other taxpayer-system failures; some penalties can also cause loss of exemptions/rates/encouragements for the relevant year.

Article 192 also imposes a 30% non-waivable penalty for failure to file a return in cases where a legal entity or business taxpayer is required to file, and applies to concealed income and unreal/non-allowable expenses in the relevant circumstances.

For Dakhl, the product must therefore behave like a **continuous pre-audit system** rather than a passive ledger.

---

## 2. What the current Dakhl architecture already provides

The current system is unusually well-positioned for this effort because its accounting spine is already a real document/transaction system with balanced posting and derived customer balances. The benchmark audit concluded that Dakhl should not replace the accounting engine; it should add structures around it.

The current application already contains:

- invoices and returns;
- payments;
- cheques and guarantee cheques;
- customers/suppliers in a shared model;
- products/services;
- warehouse and COGS;
- documents and transactions;
- bank accounts;
- Moadian integration;
- VAT fields;
- reports;
- fiscal-year transfer/export;
- payroll/HR;
- audit logging;
- backups;
- NativePHP offline desktop packaging;
- API token infrastructure.

This means a tax layer can be added **without replacing the accounting spine**.

---

## 3. The product that should be built: Tax Compliance Engine

The recommended architecture is:

```text
                       Dakhl
                           |
          +----------------+----------------+
          |                                 |
    Accounting Core                    Tax Layer
          |                                 |
 Document / Transaction          Tax Rule Engine
 Subject / Invoice               Tax Profile
 Payment / Bank                  E-Invoice Engine
 Cheque / Inventory              VAT Engine
          |                       Tax Deductibility
          |                       Filing Builder
          +------------+---------- Reconciliation
                       |
                       v
               Tax Compliance Ledger
                       |
       +---------------+---------------+
       |               |               |
       v               v               v
 Taxpayer System     Bank/POS       Evidence Vault
       |               |               |
       +---------------+---------------+
                       |
                       v
                Tax Return / Filing
```

### Core principle

**One business event → one source transaction → multiple controlled outputs.**

Example:

```text
Sale 100,000,000 IRR
  |
  +--> Accounting revenue
  +--> Customer receivable / cash
  +--> Inventory movement / COGS
  +--> Output VAT
  +--> Electronic invoice
  +--> Taxpayer-system status
  +--> Tax-report bucket
  +--> Evidence chain
```

The user should never have to re-enter the same fact into a separate "tax module."

---

## 4. Required modules

### 4.1 Tax Profile

Every company/person should have a structured tax profile:

- taxpayer type: individual / legal entity / partnership where applicable;
- tax year;
- tax registration identifiers;
- activity codes and business lines;
- VAT status;
- taxpayer-system status;
- taxpayer-system memory / endpoint configuration as legally required;
- bank accounts used for business;
- taxpayer group/classification;
- applicable tax incentives/exemptions;
- filing obligations and deadlines;
- applicable accounting/book regime;
- authorized signatories / filing users.

This profile must be versioned by effective date.

### 4.2 Tax Rule Engine

Tax rules should be data, not code constants.

```text
TaxRule
  jurisdiction = IR
  tax = income_tax | VAT | payroll | penalty
  taxpayer_type
  activity/category
  effective_from
  effective_to
  base_rule
  rate / bracket
  exemption_rule
  evidence_rule
  filing_rule
  source_reference
```

This is essential because annual budgets, amendments, circulars and official notices can change rates, thresholds and deadlines.

### 4.3 E-Invoice Engine

Invoices should carry a tax-life-cycle independent of the accounting status:

```text
Draft
 -> Validated
 -> Signed/Tax-Ready
 -> Sent
 -> Accepted / Rejected / Pending
 -> Corrected / Returned / Cancelled
```

The engine must record:

- unique tax number;
- invoice type and applicable pattern;
- buyer identity and economic/tax identifier when required;
- tax lines;
- reference invoice;
- correction/return relation;
- send timestamp;
- tax-system response;
- error codes/messages;
- retry history;
- final authority response.

It must be idempotent: retrying a network request cannot create a duplicate financial/tax event.

### 4.4 VAT Engine

For every purchase/sale line:

```text
Tax base
× applicable rate
= tax amount

input/output classification
+ electronic-invoice eligibility
+ exemption/special-rule reason
```

The engine should calculate a period VAT position:

```text
Output VAT
- Eligible Input VAT
- allowed adjustments
+ / - required corrections
= VAT payable / credit
```

The calculation should also show why every amount is included or excluded.

### 4.5 Tax Deductibility Engine

Each expense must have both an accounting classification and a tax classification.

Example:

```text
Expense
  |
  +--> Accounting: Marketing Expense
  |
  +--> Tax: potentially deductible
  |
  +--> Evidence: invoice + payment + contract
  |
  +--> Compliance: electronic invoice? yes/no
  |
  +--> Deductibility status: accepted / review / non-deductible
```

This is how the software can continuously answer:

> "Which expenses are reducing accounting profit but may not reduce taxable income?"

That is more valuable than simply computing a tax number.

### 4.6 Asset & Tax Depreciation

Separate book depreciation from tax depreciation when their rules differ.

```text
Asset
  |
  +--> Accounting depreciation
  +--> Tax depreciation
  +--> Remaining tax basis
  +--> Disposal gain/loss impact
```

### 4.7 Payment / Bank / POS Reconciliation

The benchmark already identified payment allocation and bank reconciliation as major maturity gaps.

For tax compliance, the chain becomes:

```text
Invoice
   |
   v
Payment / Bank movement
   |
   v
Allocation / Reconciliation
   |
   v
Tax evidence
```

The software should flag:

- sale without a matching payment when expected;
- payment without an identified source;
- business bank movement not linked to a document;
- invoice amount versus POS settlement mismatch;
- customer credit not allocated;
- cheque settlement inconsistencies;
- suspicious duplicate postings.

### 4.8 Tax Reconciliation Center

This should be a first-class screen.

```text
                TAX RECONCILIATION

Dakhl books      Taxpayer System      Bank/POS
     |                    |                  |
     +--------- compare / match ------------+
                         |
                         v
                    Exceptions
```

Examples:

- Dakhl sales = 1.2B; taxpayer-system sales = 1.15B → investigate 50M.
- Input VAT in books but no eligible electronic invoice → review.
- Bank credit with no invoice/payment → review.
- Taxpayer-system invoice rejected → block "tax-complete" status.
- Customer identity mismatch → require correction.

### 4.9 Filing Builder

At year/period end the user should see a generated tax package:

```text
Performance Tax
VAT Return / Period Summary
Payroll Tax
Withholding / related obligations where applicable
Bank & Payment Reconciliation
Asset/Depreciation Schedule
Inventory / COGS Reconciliation
Exemption/Tax Incentive Schedule
```

The package should be traceable back to source records.

### 4.10 Evidence Vault

Instead of an accountant asking for a mountain of files, Dakhl should continuously build the evidence chain.

For each material tax item:

```text
Tax Item
  |
  +--> source invoice
  +--> accounting document
  +--> transaction(s)
  +--> payment
  +--> bank statement
  +--> contract / supporting file
  +--> tax-system status
  +--> correction/reversal history
  +--> user/audit history
```

This is the product's answer to "where is the document that proves this number?"

---

## 5. Compliance status should be continuous, not annual

The ideal product experience is a traffic-light control center:

```text
GREEN  = tax-ready
YELLOW = review needed
RED    = tax exposure / blocked filing
```

Examples of RED controls:

- electronic invoice not sent;
- rejected tax invoice unresolved;
- input VAT claimed without eligible evidence;
- business bank account not declared;
- books and taxpayer-system sales differ materially;
- invoice correction chain broken;
- tax return cannot reconcile to books.

A taxpayer should learn about the problem the day it happens, not next summer.

---

## 6. What “no accountant needed” can realistically mean

### Realistic product promise

For a compliant SMB taxpayer, Dakhl can aim to make these routine tasks self-service:

- record sales/purchases/expenses;
- issue compliant electronic invoices;
- maintain accounting postings;
- calculate VAT;
- track deductible/non-deductible expenses;
- maintain assets/depreciation;
- reconcile payments and bank movements;
- prepare period and annual tax data;
- compare books to taxpayer-system information;
- identify missing evidence;
- produce a filing-ready package;
- maintain the audit trail.

### What should not be promised

The software should not promise that no taxpayer will ever be audited or that the exact final tax assessment is guaranteed by software alone. The law preserves audit/verification powers and allows tax disputes/appeals. Article 237 also requires a tax assessment to be based on correct bases and sufficient evidence, and Article 238 provides a statutory objection path.

The right commercial position is:

> **“Dakhl removes routine tax-preparation work; professional review remains available for exceptional, disputed, or high-risk cases.”**

That is both safer and more credible.

---

## 7. Highest-value tax features to prioritize

### P0 — Compliance protection

1. Tax Profile.
2. Tax Rule Registry with effective dates and source references.
3. E-invoice compliance state machine.
4. VAT classification engine.
5. Tax-deductibility classification.
6. Continuous missing-data checks.
7. Immutable tax/audit event history.
8. Taxpayer-system reconciliation.

### P1 — Filing automation

1. Prefill/reconciliation engine for performance tax.
2. VAT return builder.
3. Tax-ready annual package.
4. Tax evidence vault.
5. Bank/POS reconciliation.
6. Payment Allocation.
7. AR/AP ageing.

### P2 — Advanced tax intelligence

1. Risk scoring.
2. Automatic exception explanations.
3. Tax projection / cash planning.
4. Scenario simulation.
5. Asset tax depreciation.
6. Multi-activity / multi-branch tax allocation.
7. Tax incentive optimization subject to current law.

### P3 — Platform / ecosystem

1. Official API / taxpayer-system integration.
2. Webhooks and event log.
3. OpenAPI.
4. Accountant/advisor collaboration mode.
5. Tax authority / court evidence export.
6. Controlled AI assistant for tax explanations and checklists.

---

## 8. How this changes Dakhl's accounting model

Do **not** create a second ledger.

Keep:

```text
Subject
Document
Transaction
```

Add a tax interpretation layer around them:

```text
Transaction
   |
   +--> TaxClassification
   +--> TaxEvidence
   +--> TaxPeriod
   +--> TaxRuleVersion
   +--> TaxpayerSystemReference
```

This preserves the existing accounting truth while allowing tax logic to evolve independently.

For invoices:

```text
Invoice
  |
  +--> Accounting Document
  +--> VAT / Tax breakdown
  +--> Electronic invoice payload
  +--> Taxpayer-system lifecycle
  +--> Filing bucket
```

For payments:

```text
Payment
  |
  +--> accounting settlement
  +--> invoice allocation
  +--> bank reconciliation
  +--> tax evidence
```

This is exactly the separation suggested by the earlier benchmark: money movement, allocation/reconciliation, and operational document state should not be conflated.

---

## 9. The most important product screen

### “پرونده مالیاتی من” / “My Tax File”

This should eventually become one of Dakhl's defining screens.

```text
┌──────────────────────────────────────────────┐
│ وضعیت پرونده مالیاتی                         │
├──────────────────────────────────────────────┤
│ امتیاز آمادگی مالیاتی: 94 / 100             │
│ وضعیت: آماده ارسال                           │
├──────────────────────────────────────────────┤
│ فروش ثبت‌شده       12,450,000,000            │
│ فروش سامانه        12,447,000,000            │
│ اختلاف                     3,000,000  ⚠      │
│                                              │
│ VAT خروجی           1,245,000,000            │
│ VAT ورودی قابل قبول   780,000,000            │
│ VAT قابل پرداخت       465,000,000            │
│                                              │
│ فاکتور الکترونیکی      99.8%                 │
│ مدارک ناقص                3   ⚠             │
│ مغایرت بانکی              1   ⚠             │
│                                              │
│ [جزئیات مغایرت‌ها]   [آماده‌سازی اظهارنامه] │
└──────────────────────────────────────────────┘
```

The key is traceability. Every figure should be clickable back to invoices, documents, transactions and evidence.

---

## 10. Critical compliance architecture rules

### Rule 1 — No tax calculation without provenance

Every tax number must explain which source records produced it.

### Rule 2 — No mutable history for posted tax events

Corrections should create a traceable correction/reversal chain rather than silently rewriting history.

### Rule 3 — Every tax rule is time-versioned

Budget changes, circulars and effective dates must not rewrite history.

### Rule 4 — Taxpayer-system state is external truth

Dakhl may be the accounting source of truth, but official acceptance/rejection of electronic invoices belongs to the tax authority's system.

### Rule 5 — Retry must be idempotent

A network retry must never double-create an invoice, payment, VAT amount or tax event.

### Rule 6 — No "magic" tax optimization

The system should only apply documented exemptions, deductions and incentives that are actually available to that taxpayer and tax year.

### Rule 7 — Evidence is retained even when presentation is legally exempt

Article 20 exemption is not a reason to destroy evidence. Retention remains a product responsibility.

---

## 11. Recommended Dakhl roadmap addition

The existing roadmap should gain a dedicated major phase:

# TAX COMPLIANCE 1.0 — Iran

### Phase A — Regulatory foundation
- current-law registry;
- taxpayer profile;
- annual thresholds/rates;
- tax classifications;
- official-source references;
- effective-date/versioning.

### Phase B — Invoice compliance
- electronic invoice state machine;
- invoice patterns/types;
- corrections/returns;
- taxpayer-system response tracking;
- idempotent retries.

### Phase C — VAT compliance
- output/input VAT engine;
- exemption/special rules;
- credit eligibility;
- period closing;
- VAT reconciliation.

### Phase D — Performance tax
- taxable-profit engine;
- tax-deductibility engine;
- depreciation;
- Article 100 regime where applicable;
- return-builder/reconciliation.

### Phase E — Reconciliation
- bank/POS;
- payment allocation;
- taxpayer-system vs books;
- customer/supplier balances;
- exception management.

### Phase F — Evidence / audit readiness
- evidence vault;
- audit trail;
- source-to-return drill-down;
- tax package export;
- controlled advisor access.

### Phase G — Official ecosystem integration
- official API/provider integration;
- formal product approval/qualification path under applicable regulations;
- OpenAPI;
- webhooks/events.

---

## 12. Sources and current-state notes

1. **Iranian Store Terminals and Taxpayer System Law**, including current amendments reflected in the consolidated text. Key provisions: Articles 19–20 (prefilled/validated performance tax and conditional exemption from presenting books/documents), Article 22 (penalties). Source: Nezamat / legal consolidation. https://nezamat.ir/post-41585/ and https://law-ir.ir/law/cat/1769/

2. **Direct Taxes Law**, current consolidated text. Key provisions: Articles 101, 105, 131, 147–149, 192, 237–238. Source: Nezamat consolidated text and specialist law databases. https://nezamat.ir/post-28895/ ; https://services.iranimpex.com/rules.php?law_id=21

3. **Article 95 regulation**, Organization of Tax Affairs, dated 1404/08/21. This is important because it is newer than older bookkeeping guidance and explicitly addresses electronic commercial books and the continuing obligation to retain records/documents. https://hoghogh.ai/circulars/c-0981bd5b

4. **1405 tax-budget implementation circular**, Organization of Tax Affairs, 1005/200 dated 1405/01/31. Confirms the existence of current 1405 tax-budget implementation instructions. https://hoghogh.ai/circulars/c-059ba9aa

5. **1405 VAT current-rate materials**, cross-checked against the 1405 budget rule raising the total VAT rate to 10%. https://sarhaditax.com/m-budget-law-1405-p2/ and related 1405 materials.

6. **Current 1405 implementation evidence for Article 100 / prefilled performance returns**, including low-risk/prepared-return operation for performance 1404 and the temporary/current 70 billion toman sales threshold discussed by the tax authority in 1405. https://1ghazi.pro/item/4773098 and https://www.tasnimnews.ir/fa/news/1405/02/06/3575699/

7. **Current penalty handling guidance**, including 1405 amendment to the Article 22 penalty-implementation circular. https://gzita.com/law/tax/txs5000149556/

8. **Article 20 historical instruction and Administrative Justice Court annulment** of parts of the 1399 implementation instruction. The annulment is relevant because Dakhl should not hard-code invalid extra conditions from the old instruction. https://maliatman.com/%D8%A7%D8%A8%D8%B7%D8%A7%D9%84-%D8%AC%D8%B2%D8%A1-%D8%AB-%D9%88-%D8%AF%D8%B1%D8%A7%D9% ... (source title: ابطال جزء ث و ح دستورالعمل شماره 29716)

9. **Current 1405 VAT/Taxpayer-System operational notices**, including buyer response windows, prefilled VAT details, and current-period operational notices. https://taxiniran.com/category/tax-returns/

### Evidence-quality note

Iranian tax administration changes frequently through annual budgets, amendments, instructions and operational notices. This report therefore uses the permanent laws plus the newest 1404–1405 materials located during this research. Before shipping a production tax engine, every rule that directly changes a filing/calculation must be bound to its current official circular/instruction and effective date, with a maintained legal-source record inside Dakhl.

---

## Final recommendation

The tax problem should become one of Dakhl's central product differentiators.

Do not build “a tax calculator.” Build a **continuous tax-compliance system** in which accounting, electronic invoices, VAT, payment/bank evidence, deductibility, depreciation, taxpayer-system status, reconciliation and filing are all connected.

The key product outcome is not:

> “Here is your tax amount.”

It is:

> **“Here is your tax amount, here is why it is this amount, here is every source transaction behind it, here is the corresponding official-tax-system status, here are every exception and missing document, and here is the filing-ready result.”**

That is the architecture capable of materially reducing the user's dependency on a year-end accountant while remaining honest about the legal limits of software automation.
