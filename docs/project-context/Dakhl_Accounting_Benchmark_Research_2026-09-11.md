# Dakhl — Accounting & ERP Benchmark Research

## Executive verdict

Dakhl already has a **real double-entry accounting core** and a surprisingly broad ERP surface for a project at this stage. The strongest architectural decision is the use of `Document + Transaction` as the accounting source of truth, with approval-time balance enforcement and customer balances derived from transactions rather than a cached balance field.

The main problem is not that Dakhl is missing a large number of screens. The main problem is that several **second-order accounting capabilities** are not mature yet: payment allocation/reconciliation, bank reconciliation, cheque settlement semantics, immutable posted history, analytic dimensions, multi-currency, recurring automation, and stronger inventory/asset workflows.

The recommended direction is therefore **not a rewrite**. Keep the current accounting spine, harden it, then add the capabilities that mature systems place around that spine.

---

## 1. Current Dakhl baseline

### Core architecture

- Laravel 12, PHP ^8.2.
- Vite 7 + Tailwind CSS 4 + DaisyUI 5 + Alpine.js + Chart.js.
- MariaDB/MySQL for server/dev use; SQLite for tests and NativePHP desktop use.
- Session-based Laravel authentication and Spatie permissions.
- Laravel Sanctum is installed for API tokens.
- NativePHP/Electron provides the Windows desktop packaging path.

### Accounting spine

```text
Operational records
    |
    +-- Customer / Supplier
    +-- Sales Invoice
    +-- Purchase Invoice
    +-- Payment
    +-- Cheque
    +-- Ancillary Cost
    |
    v
Document
    |
    v
Transactions
    |
    +-- Debit / Credit
    +-- Subject
    |
    v
Reports / balances / ledger / trial balance
```

Current audit evidence shows that document approval enforces `SUM(transactions.value) = 0`; negative values represent debits and positive values credits. Customer balances are calculated through `SubjectService::sumSubject()` from the transactions table. The legacy `customers.balance` field is not the source of truth.

### Current functional surface

Customers, suppliers, sales and purchase invoices, returns, void invoices, payments, cheques, guarantee cheques, bank accounts, products, services, warehouses, weighted-average COGS, reports, budgets, HR, payroll, attendance, CRM, Moadian integration, backups/import-export, audit logging, and Windows NativePHP packaging are already present.

---

## 2. Benchmark set

The comparison focuses on products whose public code or documentation exposes accounting architecture and business workflows:

1. **ERPNext** — closest benchmark for a traditional open ERP accounting architecture.
2. **Odoo** — mature integrated ERP/accounting benchmark.
3. **Akaunting** — closer to small-business accounting UX and banking workflows.
4. **Dolibarr** — broad open ERP with accounting, stock, CRM and API capabilities.
5. **Firefly III** — useful benchmark for API-first finance automation and rules, though it is a personal-finance product rather than an ERP.
6. **InvoicePlane** — useful lower-complexity invoice/client/payment benchmark.

The purpose is not to copy any implementation. It is to identify mature patterns that can be adapted to Dakhl without damaging its current accounting core.

---

## 3. Comparison matrix

| Capability | Dakhl | ERPNext | Odoo | Akaunting | Main implication |
|---|---|---|---|---|---|
| Double-entry core | **Strong** | Strong | Strong | Strong | Keep Dakhl core |
| Single transaction source of truth | **Strong** | Strong GL + Payment Ledger | Strong journal architecture | Strong | Preserve architecture |
| Invoice lifecycle | **Strong** | Very strong | Very strong | Strong | Add workflow depth, not rewrite |
| Partial payments | **Yes** | Yes | Yes | Yes | Already good |
| Multi-invoice payment allocation | **Limited** | **Strong** | **Strong** | **Strong** | High-priority gap |
| Payment reconciliation | **Limited** | **Strong** | **Strong** | **Strong** | High-priority gap |
| Bank statement reconciliation | **Not mature** | **Strong** | **Strong** | **Strong** | Major maturity gap |
| Cheque/guarantee lifecycle | **Specialized but incomplete** | Generic payment workflows | Checks + payment matching | Generic | Dakhl can differentiate after fixing semantics |
| Analytic dimensions | **Not clearly implemented** | Strong | Strong | More limited | Add later without polluting chart of accounts |
| Recurring invoices/transactions | **Not evident as a mature subsystem** | Supported ecosystem | Strong | Strong | Important automation feature |
| Multi-currency | **Limited/basic** | Strong | Strong | Supported | Important for broader market |
| Fixed assets | **Not evident as a dedicated core module** | Strong | Strong | App ecosystem | Add later |
| Inventory depth | **Good foundation** | Very strong | Very strong | Moderate | Add receipts/reservations/lots/barcodes gradually |
| HR/payroll | **Present** | Strong | Strong | App-based | Dakhl already broad |
| Reporting | **Strong core reports** | Very strong | Very strong | Strong SMB reporting | Add aging/drill-down/reconciliation views |
| Audit/immutability | Activity logs present | Mature patterns | Strong controls | Good | Strengthen posted-document lifecycle |
| REST API | **Token infrastructure exists; endpoint surface needs dedicated audit** | Strong | Strong | Strong | Formalize API v1 |
| Native offline Windows | **Major strength** | Primarily web/self-hosted | Primarily web/self-hosted | Primarily web/self-hosted | Keep as differentiator |
| Iranian/Jalali/Moadian fit | **Major strength** | Localization dependent | Localization dependent | Localization dependent | Protect this advantage |

---

## 4. What Dakhl is already doing well

### 4.1 The accounting spine is sound

Dakhl's strongest feature is not its UI; it is its accounting model. The system has a real document/transaction posting layer and a balance invariant at approval. This is conceptually aligned with ERPNext's General Ledger architecture and Odoo's journal-entry model.

ERPNext explicitly describes operational documents turning into balanced ledger entries and separates source documents, GL entries, and a Payment Ledger for allocation/reconciliation. Odoo likewise states that accounting transactions automatically create balanced journal entries.

This means Dakhl does **not** need a replacement accounting engine. It needs more structures around it.

### 4.2 Customer balance is derived, not manually maintained

Using the transactions table as the source of truth is the right direction. It reduces the risk of stale `customer.balance` values and makes reports more explainable.

### 4.3 Local product fit is a genuine competitive advantage

Persian/RTL, Jalali dates, Persian fonts, Iranian Moadian integration, and a self-contained Windows desktop path create a substantially different value proposition from generic open ERP projects.

### 4.4 Current module breadth is already high

The combination of invoices + payments + cheques + warehouses/COGS + reports + HR/payroll + CRM + backups means Dakhl already sits closer to an integrated SMB ERP than to a simple invoicing application.

---

## 5. Highest-priority weakness: payment allocation and reconciliation

This is the biggest architectural gap exposed by the benchmark.

Dakhl currently has payments linked to invoices and uses `document_id` as part of its definition of a settled/accounted payment. That works for simple cases, including partial payments, but it is not the same as a first-class **allocation/reconciliation layer**.

ERPNext has a separate Payment Ledger and Payment Reconciliation tool. It can allocate one payment across several invoices, settle one invoice through several payments, and allocate only part of a payment while leaving the remainder unallocated. Odoo similarly supports standalone payments, multiple payments per invoice, group payments and payment matching. Akaunting supports connecting and splitting banking transactions to invoices/bills.

### Recommended Dakhl model

Introduce a concept equivalent to:

```text
Payment
   |
   +---- PaymentAllocation ----> Invoice
              amount
              allocated_at
              created_by
              reference
```

This should **not replace** the existing Payment or Transaction tables. It should explain how an existing financial movement is applied to operational documents.

The result would support:

- one payment → several invoices;
- several payments → one invoice;
- partial allocation;
- unallocated customer credit;
- refunds/credit balances;
- payment correction without creating a second bank movement;
- reconciliation/audit history.

This same pattern also makes the future cheque-settlement model much cleaner.

---

## 6. Bank reconciliation should become a first-class subsystem

Dakhl already has banks, bank accounts and settlement subjects, but the benchmark systems go one layer further: they distinguish the **bank statement side** from the **book/accounting side**, then reconcile the two.

ERPNext stores Bank Transactions separately from Payment Entries/Journal Entries and reconciles the two. Odoo offers a matching view with automatic matching rules and reconciliation models. Akaunting also has bank feeds and reconciliations.

### Recommended architecture

```text
Bank Statement
     |
     v
BankTransaction
     |
     +-------> Match / Reconcile <-------+
                                         |
                                   Payment / Journal
                                         |
                                         v
                                    Transactions
```

Important rule: reconciliation should normally **not create a second financial movement**. It should connect an existing bank movement to an existing accounting event.

For Dakhl desktop/offline, the first version should probably support **CSV/Excel statement import and manual reconciliation** before attempting any live bank API connections.

---

## 7. Cheques are Dakhl's opportunity and current danger zone

The current cheque module is more specialized than the benchmark accounting products, but its semantics are not yet complete.

The audit found:

```text
Guarantee cheque
   -> correctly off-balance at registration

BUT

Execute guarantee
   -> moves the full receivable early

AND

Settlement cheque
   -> can create a payment at registration
   -> clear can create another payment
   -> bounce does not fully restore invoice/customer state
```

The missing abstraction is a real **Cheque Settlement / Allocation** layer.

### Recommended model

```text
Cheque
   |
   +---- CheckSettlement ----> Payment
              amount
              settled_at
              user_id
              notes
```

Business invariant:

```text
sum(CheckSettlement.amount) <= Cheque.amount
```

When the sum reaches the cheque amount:

```text
Cheque = Fully Settled
```

Only then should the application offer a "return cheque" action for the relevant guarantee workflow.

The return itself should not create a fake second receipt. It should represent a lifecycle/state event with an audit trail.

This is the most important structural enhancement for the cheque domain.

---

## 8. Add accounting dimensions before the chart of accounts becomes overloaded

ERPNext and Odoo both provide analytic dimensions/cost centers/projects or similar structures. ERPNext specifically positions dimensions as a way to analyze branches, business units, departments and projects without exploding the Chart of Accounts.

### Recommended Dakhl future model

Keep the existing Subject/Chart structure, but add optional dimensions such as:

```text
Branch
Department
Project
Cost Center
Sales Channel
```

Then a transaction can answer both:

> "Which account did this hit?"

and:

> "Which branch/project/department generated it?"

Do this as an extension around `Transaction`, not by changing the meaning of `Subject`.

---

## 9. Recurring invoices and scheduled automation

Akaunting supports recurring invoices and recurring bills. ERPNext and Odoo have recurring/contract/subscription workflows as well.

Dakhl should eventually support:

```text
Recurring Template
       |
       +-- schedule
       +-- customer
       +-- lines
       +-- payment terms
       +-- start/end
       v
Generated Invoice
```

For the desktop edition, this requires proper scheduler/queue behavior because there is no server constantly running in the background in the same way as a hosted deployment.

Therefore the first desktop implementation should be deterministic:

> "On application start, process due recurring tasks once."

That is safer than immediately building a complicated always-on scheduler.

---

## 10. Inventory should mature around the existing warehouse/COGS core

Dakhl already has warehouses, products/services, stock and weighted-average COGS. That is a good base.

The benchmark systems go further with capabilities such as reservations, replenishment, barcode handling, lots/serials, stock ledger depth, receipts/deliveries and purchasing workflows.

### Suggested order

1. Stock ledger visibility and audit.
2. Purchase receipt / sales delivery concepts.
3. Stock reservations.
4. Barcode support.
5. Lot/batch tracking.
6. Serial numbers.
7. Replenishment rules.

Do not build all of these at once. A small shop mainly needs stock correctness first.

---

## 11. Fixed assets are a clear future accounting module

Odoo, ERPNext and similar mature ERPs treat fixed assets as an accounting domain rather than a simple expense record.

Dakhl should eventually support:

```text
Asset
  -> acquisition
  -> activation
  -> depreciation schedule
  -> accumulated depreciation
  -> disposal / sale
  -> accounting entries
```

This is especially useful for businesses buying computers, vehicles, machinery and equipment.

It should come **after** reconciliation and cheque settlement, not before.

---

## 12. Multi-currency should be designed carefully, not patched later

Dakhl currently has a company currency concept, but it should eventually distinguish:

```text
Transaction Currency
Base Company Currency
Exchange Rate
```

and support realized/unrealized FX differences where applicable.

This becomes important even inside Iran because businesses may invoice or settle in USD, EUR, AED, CNY or other currencies while maintaining a Rial base ledger.

Designing this later is much more expensive if transaction values have already been hard-coded around one currency.

---

## 13. Financial history should become harder to destroy

Current audits identified cascade deletes from companies/invoices/documents into financial records. That is convenient for development but risky for accounting software.

The mature pattern should be:

```text
Draft       -> editable
Approved    -> immutable
Cancelled   -> reversal/cancellation event
Archived    -> retained
```

For posted financial documents, prefer reversal/amendment workflows over hard deletion.

A useful rule for Dakhl:

> **If an accounting document has posted transactions, do not physically delete the financial event through normal UI actions.**

This should be enforced in the service layer, not merely hidden in the UI.

---

## 14. Money calculations should progressively stop using PHP float semantics

Database amounts are decimal, which is good, but some service paths use PHP `float` casts and tolerances.

This is acceptable for early development but should be hardened before serious financial deployment.

Preferred direction:

```text
DB: DECIMAL
Application arithmetic: integer minor units OR controlled decimal math
Comparisons: exact/decimal-aware
```

This matters especially when adding reconciliation, multi-currency, discounts, tax, FX and installment allocation.

---

## 15. Reports need a second generation

Dakhl already has useful core financial reports. The next level should be more decision-oriented:

- Accounts Receivable ageing.
- Accounts Payable ageing.
- Customer statement with drill-down to invoice/payment/allocation.
- Supplier statement.
- Cash flow view.
- Bank reconciliation status.
- Cheque exposure/expected collections.
- Overdue cheque/guarantee worklist.
- Profitability by analytic dimension.
- Inventory valuation and slow-moving stock.

The key design principle is **drill-down**:

```text
Dashboard number
      ↓
Report row
      ↓
Document
      ↓
Transaction
```

A user should be able to trace a number all the way back to its source document.

---

## 16. API — what the API section in the program actually does

The visible section in Dakhl is an **API token management** feature, not a second accounting system.

The current project has Laravel Sanctum installed, a `personal_access_tokens` table, and an `ApiTokenController`. The web routes expose:

```text
GET     /api-tokens
GET     /api-tokens/create
POST    /api-tokens
DELETE  /api-tokens/{tokenId}
```

These routes are protected by authentication and permission middleware. The application also explicitly registers `routes/api.php`, appends activity logging to the API middleware group, and defines an `api-company` middleware backed by `SetApiCompany`.

So the intended architecture is approximately:

```text
External program
      |
      | Authorization: API token
      v
Dakhl API
      |
      +--> Company context
      +--> Permissions
      +--> Business services
      +--> Documents / Payments / Customers / ...
      |
      v
Dakhl accounting core
```

### What it can be used for later

- POS integration.
- E-commerce/shop integration.
- Website order import.
- Mobile client.
- External warehouse system.
- Bank statement import service.
- Automation scripts.
- AI assistant actions with strict permissions.
- Integration with another company's software.

### Important distinction

The API should **not** become a parallel business-logic implementation.

The ideal architecture is:

```text
Web UI  -----\
              > Domain / Service layer -> Accounting core
API     -----/
```

not:

```text
Web UI -> custom logic A
API    -> custom logic B
```

This prevents the API from drifting away from what the desktop/web application actually does.

### API gap to address

The existence of token management does not by itself prove that the whole accounting surface is safely exposed. A dedicated API audit should enumerate the actual endpoints in `routes/api.php`, their authentication middleware, company scoping, permissions, validation, rate limits, pagination, idempotency and whether financial POST endpoints are safe against retries.

For production, I recommend an explicit versioned API:

```text
/api/v1/...
```

with:

- revocable tokens;
- token expiry or rotation policy;
- per-token/scoped permissions;
- company context that is explicit and validated;
- pagination/filtering;
- idempotency keys for financial POSTs;
- structured error responses;
- audit trail;
- webhook/event support;
- OpenAPI documentation.

---

## 17. A particularly important benchmark lesson: separate "money happened" from "money was allocated"

The most useful idea from ERPNext/Odoo/Akaunting for Dakhl is a separation of layers:

```text
Financial event
      |
      v
Payment / Bank Transaction
      |
      v
Allocation / Reconciliation
      |
      v
Invoice / Cheque / Customer balance
```

This is the pattern that solves several future requirements at once.

For example:

```text
10,000,000 customer payment
        |
        +--> Bank movement = 10,000,000
        |
        +--> Allocate 2,000,000 to Invoice A
        +--> Allocate 3,000,000 to Invoice B
        +--> Leave 5,000,000 as customer credit
```

The bank movement is still one event. Allocation explains what that event is for.

The same concept can drive the future guarantee-cheque settlement model.

---

## 18. Recommended modernization roadmap

### P0 — Financial integrity

1. Fix the invalid-login 500 bug in the packaged desktop build.
2. Add Cheque Settlement/Allocation.
3. Fix bounced settlement cheque behavior so invoice/payment/customer state is restored correctly.
4. Prevent duplicate posting across cheque registration/clear lifecycle.
5. Harden posted-document deletion and reversal rules.
6. Remove financial arithmetic reliance on PHP float where practical.
7. Lock down production debug behavior.

### P1 — Reconciliation layer

8. PaymentAllocation entity.
9. Payment reconciliation screen.
10. Customer credit / unallocated payment balance.
11. Bank transaction import.
12. Bank reconciliation screen.
13. Customer and supplier ageing.

### P2 — Accounting maturity

14. Accounting dimensions.
15. Payment terms and installment schedules.
16. Recurring invoices/bills.
17. Fixed assets.
18. Multi-currency.
19. Richer tax engine / withholding where required.

### P3 — Operations maturity

20. Quotations.
21. Sales orders / purchase orders.
22. Delivery / receipt workflows.
23. Stock reservations.
24. Barcode support.
25. Lot/batch/serial tracking.
26. Replenishment rules.

### P4 — Platform/integration

27. `/api/v1` formalization.
28. OpenAPI documentation.
29. Webhooks/events.
30. POS/e-commerce integrations.
31. Mobile application integration.
32. AI/automation layer over the API with permission boundaries.

---

## 19. What should NOT be changed

Do not rewrite these foundations merely to resemble another product:

- `DocumentService` as the central posting layer.
- `Transaction` as the financial source of truth.
- Subject-based customer/supplier balances.
- Existing invoice transaction builder unless a measured accounting defect requires it.
- Company scoping architecture.
- Persian/Jalali/Moadian capabilities.
- NativePHP offline desktop packaging.

Benchmark the **patterns**, not the source code.

---

## 20. Bottom line

Dakhl is already beyond the "basic accounting app" stage. Its core architecture is strong enough to become the foundation of a serious SMB accounting/ERP product.

The priority should therefore be:

```text
Do not add more screens first.
        ↓
Strengthen accounting correctness.
        ↓
Add reconciliation/allocation.
        ↓
Finish cheque semantics.
        ↓
Add dimensions + automation.
        ↓
Then expand inventory/operations/integrations.
```

The benchmark evidence strongly supports a **surround-the-core modernization strategy** rather than a rewrite.

That approach also keeps the current project compatible with a future larger product idea: a clean accounting core, explicit reconciliation layer, strong API, and auditable event history make the system much easier to extend without turning every new feature into another special case.

---

# Sources

## Dakhl internal evidence

- Dakhl repository architecture/accounting audit captured in the project Library (September 2026): accounting engine, payment flow, cheque lifecycle, database schema, UI, security, API/token infrastructure.
- Dakhl `bootstrap/app.php`: API route registration, API middleware and `SetApiCompany` alias.
- Dakhl `routes/web.php`: API token management routes and permissions.
- Dakhl `composer.json`: Laravel Sanctum dependency and Laravel 12 stack.

## External benchmarks

1. ERPNext — Accounting Introduction: https://docs.frappe.io/erpnext/accounting-introduction
2. ERPNext — Payment Reconciliation: https://docs.frappe.io/erpnext/payment-reconciliation
3. ERPNext — Banking: https://docs.frappe.io/erpnext/banking-in-erpnext
4. ERPNext — Accounting Dimensions: https://docs.frappe.io/erpnext/accounting-dimensions
5. ERPNext GitHub — Accounts module: https://github.com/frappe/erpnext/blob/develop/erpnext/accounts/README.md
6. Odoo 19 — Accounting and Invoicing: https://www.odoo.com/documentation/19.0/applications/finance/accounting.html
7. Odoo 19 — Payments: https://www.odoo.com/documentation/19.0/applications/finance/accounting/payments.html
8. Odoo 19 — Bank Reconciliation: https://www.odoo.com/documentation/19.0/applications/finance/accounting/bank/reconciliation.html
9. Odoo GitHub — Accounting app: https://github.com/odoo/odoo/blob/19.0/addons/account/README.md
10. Akaunting — REST API: https://akaunting.com/hc/docs/developers/restful-api/
11. Akaunting — Banking, Feeds and Reconciliations: https://akaunting.com/hc/docs/banking-feeds-reconciliations/
12. Akaunting — Recurring Invoices: https://akaunting.com/hc/docs/invoices-and-estimates/creating-recurring-invoices/
13. Akaunting — Reconciliation: https://akaunting.com/hc/docs/banking-feeds-reconciliations/reconciling-transactions/
14. Akaunting GitHub/project documentation: https://github.com/akaunting/akaunting
15. Dolibarr — Product/project feature set and REST API documentation: https://www.dolibarr.org/ and https://wiki.dolibarr.org/
16. Firefly III GitHub — REST API, rules, recurring transactions, double-entry: https://github.com/firefly-iii/firefly-iii
17. InvoicePlane GitHub — invoices, quotes, clients, payment tracking and reporting: https://github.com/InvoicePlane/InvoicePlane

## Interpretation note

The scores and priorities in this document are analytical judgments based on the verified Dakhl audit and the documented capabilities of the benchmark projects. They are not claims that any benchmark product is defect-free; mature ERP projects also have active bugs and feature requests in their public repositories.
