# Dakhl Clean-Room Core Rewrite Plan (Option B Architecture)

**Document Reference:** `docs/cleanroom/CORE_REWRITE_PLAN.md`  
**Governing Decision:** `D-130 — Clean-Room Core Rewrite, Fresh Dakhl Schema Baseline, and UX View Rebuild Architecture`  
**Status:** DRAFT PROPOSAL FOR OWNER APPROVAL — NO REWRITE YET STARTED  
**Target Milestone:** Dakhl Clean-Room Protected-Core Replacement  
**Date:** 2026-10-29  

---

## 1. Executive Summary & Strategic Rationale

Pursuant to Decision **D-130** (approving **Option B** from `docs/cleanroom/STATUS.md`), Dakhl resolves all legacy GPL contamination through a surgical, three-pillar modernization program:

1. **Clean-Room Core Accounting Rewrite:** The ~16 protected-core services, transaction builders, and financial lifecycles are rewritten from scratch via an isolated "fresh-session" clean-room protocol, guided strictly by sanitized behavioral specifications and black-box tests.
2. **UX Modernization for Views:** Rather than mechanically rewriting 310 legacy FreeAmir Blade views (which are tightly coupled to obsolete UX patterns), views are systematically superseded by the new Persian-first Dakhl desktop interface (Tailwind CSS, DaisyUI, Alpine.js).
3. **Fresh Dakhl-Owned Schema Baseline:** The database schema is redesigned from first principles. Because Dakhl is pre-release software with zero production installations or historical client data, legacy backward-compatibility migrations are eliminated. The new schema provides strict relational constraints, explicit debit/credit accounting columns, UUID synchronization keys, and immutable audit trails.

> [!IMPORTANT]
> **Owner Release Gate:** In strict accordance with AGENTS.md Guardrail 5, no closed-source build, installer, or release may be distributed before CR-7 is completed and the Owner Release Gate is formally executed.

---

## 2. Inventory of the 16 Protected-Core Units

The core accounting engine consists of 16 interconnected services and transaction builders that govern ledger integrity, financial lifecycles, and posting immutability:

| # | Protected-Core Class | Relative Path | Primary Architectural Responsibility | Risk Level |
|---|---|---|---|:---:|
| 1 | `DocumentService` | `app/Services/DocumentService.php` | Universal double-entry journal posting choke-point; enforces document numbering, status transitions, and balanced postings. | **CRITICAL** |
| 2 | `DocumentReversalService` | `app/Services/DocumentReversalService.php` | Implements audit reversals (D-127) for posted documents; enforces closed-period permissions and reversal provenance. | **CRITICAL** |
| 3 | `InvoiceTransactionBuilder` | `app/Services/InvoiceTransactionBuilder.php` | Compiles multi-line invoice journals (sales, purchases, returns, discounts, tax/VAT, rounding, and COGS offsets). | **CRITICAL** |
| 4 | `InvoiceService` | `app/Services/InvoiceService.php` | Invoice status lifecycle (draft $\to$ approved $\to$ rejected $\to$ paid); enforces financial immutability boundary (D-086–D-089). | **CRITICAL** |
| 5 | `CostOfGoodsService` | `app/Services/CostOfGoodsService.php` | Inventory valuation engine; calculates weighted-average unit costs and perpetual inventory cost-of-goods postings. | **HIGH** |
| 6 | `PaymentService` | `app/Services/PaymentService.php` | Atomic cash/bank/card payment voucher creation; enforces financial event UUID identity (D-078/D-079) and immutability (D-084). | **CRITICAL** |
| 7 | `SettlementService` | `app/Services/SettlementService.php` | Invoice installment settlements, payment allocation lifecycles, and capacity validations (D-070/D-081). | **HIGH** |
| 8 | `ChequeService` | `app/Services/ChequeService.php` | Independent 8-state cheque instrument state machine (register, deposit, clear, bounce, endorse, return, cancel, guarantee) (D-070). | **CRITICAL** |
| 9 | `FiscalYearService` | `app/Services/FiscalYearService.php` | Fiscal year closing, closing/opening voucher generation, profit/loss rollup, and baseline import/export provenance (D-085). | **CRITICAL** |
| 10 | `SubjectService` | `app/Services/SubjectService.php` | Chart of Accounts hierarchy management (Root $\to$ Moeen $\to$ Tafsili); validates accounting nature and balance sheet permanence. | **HIGH** |
| 11 | `AncillaryCostService` | `app/Services/AncillaryCostService.php` | Inbound commercial shipping, customs, and ancillary costs capitalization on purchase invoices. | **MEDIUM** |
| 12 | `AncillaryCostTransactionBuilder` | `app/Services/AncillaryCostTransactionBuilder.php` | Constructs double-entry journals capitalizing landed ancillary costs into inventory valuations. | **MEDIUM** |
| 13 | `FixedAssetService` | `app/Services/FixedAssetService.php` | Fixed asset registration, depreciation schedules, and automated periodic depreciation journal postings. | **MEDIUM** |
| 14 | `GroupActionService` | `app/Services/GroupActionService.php` | Batch recalculation pipelines, dependency recycling, and non-financial COGS chaining (D-087). | **HIGH** |
| 15 | `PeriodLockGuard` | `app/Services/FinancialAudit/PeriodLockGuard.php` | Statutory freeze enforcement across the 5-state fiscal lifecycle (`OPEN`, `REVIEW`, `RECONCILED`, `CLOSED`, `LOCKED`) (D-128). | **CRITICAL** |
| 16 | `DocumentNumberService` | `app/Services/DocumentNumberService.php` | Atomic sequential document numbering, concurrency locking, and device range leases (D-075). | **HIGH** |

---

## 3. Mandatory Non-Negotiable Invariants & Guardrails

Every clean-room replacement must mathematically satisfy and rigorously enforce the following seven core invariants:

```
                      ┌────────────────────────────────────────┐
                      │        ACCOUNTING INVARIANTS           │
                      └───────────────────┬────────────────────┘
                                          │
         ┌────────────────────────────────┼────────────────────────────────┐
         │                                │                                │
┌────────┴────────┐              ┌────────┴────────┐              ┌────────┴────────┐
│ 1. Balance Net  │              │ 2. Immutability │              │ 3. Period Lock  │
│  SUM(value) = 0 │              │  D-084/D-086/89 │              │  5-State Freeze │
└─────────────────┘              └─────────────────┘              └─────────────────┘
         │                                │                                │
┌────────┴────────┐              ┌────────┴────────┐              ┌────────┴────────┐
│ 4. Provenance   │              │ 5. Multi-Tenant │              │ 6. Audit Trail  │
│  NATIVE/BASELINE│              │  Company Scope  │              │  Reversals Only │
└─────────────────┘              └─────────────────┘              └─────────────────┘
```

### Invariant 1: Double-Entry Mathematical Balance
Every accounting document must strictly balance debits and credits:
$$\sum \text{Debit} = \sum \text{Credit} \iff \sum_{i=1}^{n} \text{transactions.value}_i = 0$$
Unbalanced documents must be unconditionally rejected at the service/builder boundary. Partial or unbalanced documents must never reach the database.

### Invariant 2: Multi-Tenancy & Fiscal Isolation
All accounts, documents, invoices, cheques, and inventory records are strictly scoped by `company_id` and `fiscal_year_id`. Cross-tenant data leakage is prevented via global scopes (`FiscalYearScope`) and validated in FormRequests and transaction builders.

### Invariant 3: Posted Record Immutability (D-084, D-086, D-087, D-089)
Once a document, invoice, payment, or payment allocation has completed financial posting or has active financial attachments (`Payment` or `PaymentAllocation`), it is immutable:
- Destructive edits (`updateInvoice`) are blocked (D-086).
- Unapprovals (`unapproveInvoice`) are blocked (D-087).
- Re-approvals (`userFacingApproveInvoice`) are blocked (D-088).
- Direct rejections (`userFacingRejectInvoice`) are blocked (D-089).
- Hard deletions (`deletePayment`, `removeAllocation`, `deleteInvoice`) are unconditionally blocked (D-084).

### Invariant 4: Audit Trail & Provenance (D-085)
Every financial record carries immutable provenance:
- `FinancialProvenance::NATIVE`: Created directly by user operations.
- `FinancialProvenance::BASELINE`: Imported or copied from a prior fiscal year.
- `FinancialProvenance::REVERSAL`: Automatically generated accounting reversal (D-127).

### Invariant 5: Fiscal Period Freeze (D-128)
The fiscal year follows an auditable 5-state lifecycle:
$$\text{OPEN} \longrightarrow \text{REVIEW} \longrightarrow \text{RECONCILED} \longrightarrow \text{CLOSED} \longrightarrow \text{LOCKED}$$
All mutation attempts in `CLOSED` or `LOCKED` periods are blocked by `PeriodLockGuard`. Adjustments in `CLOSED` periods require elevated permissions and audit reasons; `LOCKED` periods are strictly immutable.

### Invariant 6: Official Audit Reversals over Deletion (D-127)
Erroneous posted transactions must never be deleted or silently edited. Correction must follow the official accounting audit trail: generating a dedicated reversing voucher (`is_reversal = true`) that negates the original postings, linked by `reversed_document_id`.

### Invariant 7: Exactly-Once Financial Event Identity (D-078, D-079)
Payments and financial events carry an immutable `financial_event_uuid`, identifying the economic event independently of retry attempts (`operation_id`). Re-submitting the same economic event is strictly idempotent.

---

## 4. Public Interfaces & Type Contracts to Preserve

To ensure seamless integration with the existing controller layer, queued jobs, and frontend APIs, the rewritten services must maintain the following core public contracts:

```php
namespace App\Contracts\Accounting;

interface DocumentServiceInterface
{
    public static function createDocument(User $user, array $headerData, array $transactionLines): Document;
    public static function updateDocument(Document $document, User $user, array $headerData, array $transactionLines): Document;
    public static function changeDocumentStatus(Document $document, User $user, string $status): Document;
}

interface InvoiceTransactionBuilderInterface
{
    public static function build(Invoice $invoice, array $context = []): array;
}

interface PaymentServiceInterface
{
    public function createPayment(User $user, array $attributes): Payment;
    public function createCreditPayment(User $user, array $attributes): Payment;
    public function allocatePayment(Payment $payment, Invoice $invoice, float $amount): PaymentAllocation;
}

interface ChequeServiceInterface
{
    public function registerCheque(User $user, array $attributes): Cheque;
    public function depositCheque(Cheque $cheque, int $bankAccountId): Cheque;
    public function clearCheque(Cheque $cheque): Cheque;
    public function bounceCheque(Cheque $cheque, string $reason): Cheque;
    public function endorseCheque(Cheque $cheque, int $recipientId): Cheque;
    public function returnCheque(Cheque $cheque): Cheque;
    public function cancelCheque(Cheque $cheque): Cheque;
}
```

---

## 5. New Dakhl Baseline Schema Redesign Proposal

### 5.1 Architecture Principles
1. **Explicit Debit / Credit Columns:** Moving away from signed `value` floats toward explicit `debit_amount` and `credit_amount` decimal columns (`DECIMAL(18, 4)`), satisfying international accounting audit standards and preventing sign-flipping bugs.
2. **UUID Primary & Secondary Keys:** All financial aggregate roots carry canonical `uuid` keys for offline/online synchronization (D-071/D-078).
3. **Engine-Level Relational Integrity:** Enforcing `ON DELETE RESTRICT` on all foreign keys referencing posted transactions, accounts, and companies.
4. **Clean Domain Terminology:** Replacing ambiguous upstream names with standard accounting terms:
   - `documents` $\to$ `accounting_vouchers`
   - `transactions` $\to$ `voucher_lines`
   - `subjects` $\to$ `accounts`
   - `cheques` $\to$ `cheque_instruments`
   - `payments` $\to$ `treasury_payments`

### 5.2 Target Dakhl Baseline Schema (Quarantine & Clean-Room Rule)

> [!IMPORTANT]
> **Clean-Room Isolation Rule for Database Schema:**  
> The old-to-new mapping table contains derived legacy structures and is strictly quarantined in `docs/cleanroom/RESTRICTED_UPSTREAM_MAPPING.md`.  
> In accordance with CR-3 governance, **Implementer sessions must never receive `RESTRICTED_UPSTREAM_MAPPING.md` or any legacy schema definitions**.  
> The Implementer receives **EXCLUSIVELY** the new target Dakhl schema definition, sanitized behavioral specifications, and black-box tests.

#### Target Dakhl Baseline Schema Summary
- **`companies`**: Multi-tenant company context and configuration.
- **`fiscal_periods`**: 5-state fiscal calendar cycles (`OPEN`, `REVIEW`, `RECONCILED`, `CLOSED`, `LOCKED`).
- **`accounts`**: 3-tier Chart of Accounts (Root $\to$ Moeen $\to$ Tafsili), financial balance nature (`debtor`/`creditor`), and permanence flag.
- **`accounting_vouchers`**: Universal double-entry journal vouchers with immutable numbers, posting provenance (`native`, `baseline`, `reversal`), and sync UUID.
- **`voucher_lines`**: Journal lines with explicit non-negative `debit_amount` and `credit_amount` (`DECIMAL(18, 4)`), target `account_id`, and foreign key constraint (`ON DELETE RESTRICT`).
- **`invoices` & `invoice_lines`**: Commercial sales, purchases, and return invoices with sequential leasing (D-075) and mutation boundary enforcement (D-086–D-089).
- **`treasury_payments`**: Cash, bank, and electronic payments carrying canonical `financial_event_uuid` (D-078/D-079) and posting immutability (D-084).
- **`payment_allocations`**: Invoice settlement allocation records linking payments to invoice installments (D-081).
- **`cheque_instruments`**: Independent 8-state commercial paper instrument register (D-070).

---

### 5.3 Mermaid Schema Relationship Diagram

```mermaid
erDiagram
    COMPANIES ||--o{ FISCAL_PERIODS : "operates within"
    COMPANIES ||--o{ ACCOUNTS : "owns"
    COMPANIES ||--o{ ACCOUNTING_VOUCHERS : "records"
    COMPANIES ||--o{ INVOICES : "issues/receives"
    COMPANIES ||--o{ TREASURY_PAYMENTS : "executes"
    COMPANIES ||--o{ CHEQUE_INSTRUMENTS : "holds"

    ACCOUNTS ||--o{ ACCOUNTS : "hierarchical parent"
    ACCOUNTS ||--o{ VOUCHER_LINES : "posted into"

    ACCOUNTING_VOUCHERS ||--|{ VOUCHER_LINES : "contains (balanced)"
    FISCAL_PERIODS ||--o{ ACCOUNTING_VOUCHERS : "locks/contains"

    INVOICES ||--o{ INVOICE_LINES : "itemizes"
    INVOICES ||--o| ACCOUNTING_VOUCHERS : "generates upon approval"

    TREASURY_PAYMENTS ||--o| ACCOUNTING_VOUCHERS : "posts atomically"
    TREASURY_PAYMENTS ||--o{ PAYMENT_ALLOCATIONS : "allocates"
    INVOICES ||--o{ PAYMENT_ALLOCATIONS : "settled by"

    CHEQUE_INSTRUMENTS ||--o{ ACCOUNTING_VOUCHERS : "posts lifecycle events"
```

---

### 5.4 Owner-Approved Schema Architecture Decision Records (ADRs D-131 .. D-135)

To guarantee architectural stability and rigorous accounting integrity, the Owner has formally approved five foundational Architecture Decision Records (Decisions D-131 through D-135 in `Dakhl_DECISION_LOG_2026-09-12-r4.md`):

#### ADR-A (Decision D-131): Explicit Dual `debit_amount` / `credit_amount` Columns & Balance Policy
- **Decision:** Permanent retirement of legacy single signed `value`. Dual unsigned `debit_amount` and `credit_amount` columns.
- **Zero-Amount Line Policy:** Lines with `debit_amount == 0 AND credit_amount == 0` are strictly forbidden and rejected at domain and DB levels. Every line must have an amount on exactly one side:
  $$((\text{debit} > 0 \land \text{credit} = 0) \lor (\text{debit} = 0 \land \text{credit} > 0))$$
- **Database CHECK Constraints:** Active on both MariaDB and SQLite:
  `CHECK ((debit_amount = 0 AND credit_amount > 0) OR (debit_amount > 0 AND credit_amount = 0))`
- **API v1 / Sync Compatibility Plan:**
  - Inbound/outbound API v1 adapter converts dual columns to/from signed `value` for backward compatibility before releasing Android companion.
  - Sync Protocol v2 adopts native dual debit/credit columns directly.

#### ADR-B (Decision D-132): Monetary Representation, Half-Up Rounding & Precision Boundaries
- **Monetary Storage (Rials):** All ledger monetary columns are whole integer Iranian Rials stored in `BIGINT`. No floats/decimals for ledger money.
- **Display Concept (Toman):** Toman is strictly a presentation-layer display format ($1 \text{ Toman} = 10 \text{ Rials}$), never stored in the database.
- **Rounding Strategy:** Commercial and statutory Iranian tax **Half-Up (away from zero)** via centralized `App\Support\Money::round()` choke point, strictly rejecting banker's rounding (`ROUND_HALF_EVEN`) which conflicts with Moadian / tax compliance.
- **Foreign Currency & Gold Ledger:**
  - Foreign Currency: `foreign_amount DECIMAL(18, 4)`, `exchange_rate DECIMAL(18, 4)`.
  - Gold / Precious Metals: `gold_weight DECIMAL(18, 4)` (supporting statutory $0.0001 \text{ g}$ precision), `gold_rate BIGINT UNSIGNED` (Rials per gram).
- **Inventory Precision:** Uniform `DECIMAL(18, 6)` quantity across all inventory transactions and bill of materials.

#### ADR-C (Decision D-133): Primary Key Strategy (BIGINT Clustered PK + Secondary Sync UUIDv7)
- **Decision:** Clustered sequential `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` on MariaDB/InnoDB for highest B-tree compaction and local join throughput.
- **Distributed Identity:** Indexed `BINARY(16) uuid` storing time-ordered **UUIDv7 (RFC 9562)** on all distributed entities for collision-free offline synchronization.

#### ADR-D (Decision D-134): Universal Relational Integrity (`ON DELETE RESTRICT`) & Exception Policy
- **Decision:** Universal **`ON DELETE RESTRICT`** across all financial, voucher, invoice, payment, tax, and inventory tables.
- **Exception List for Non-Financial Tables:**
  - `personal_access_tokens` (`cascadeOnDelete()`)
  - `job_batches`, `failed_jobs`, `cache`, `sessions`
  - `user_preferences` / UI state
- **Company Deletion SOP:** Permitted only if company has zero native approved documents (`FinancialProvenance::NATIVE`, D-085) and zero payment allocations.
- **Fiscal-Year Rollover SOP:** Rollover never re-parents foreign keys; transfers closing balances via statutory opening vouchers (`BASELINE`) via `FiscalYearService::transferClosingBalances()`.

#### ADR-E (Decision D-135): Database-Level Defense-in-Depth Immutability Triggers
- **Decision:** In addition to application-layer guards, DB-level immutability triggers (`BEFORE UPDATE` and `BEFORE DELETE`) are installed on posted documents and transaction lines across both SQLite and MariaDB:
  - If parent document `approved_at IS NOT NULL`, any modification or deletion raises an engine-level error (`SIGNAL SQLSTATE '45000'`).
- **Engine Verification:** Integration test suite validates that raw SQL deletes/updates on approved records fail at the database level on both SQLite and MariaDB `dakhl_test`.

---

## 6. The Clean-Room Fresh-Session Protocol

To guarantee mathematical independence, complete copyright hygiene, and eliminate contaminated memory/context carryover, core units must be implemented through the following formal 4-role protocol:

```
┌────────────────────────────────────────────────────────────────────────┐
│                      CLEAN-ROOM EXECUTION PIPELINE                     │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ 1. ROLE: ANALYST                                                       │
│    - Examines statutory tax rules, accounting laws, and Dakhl behavior.│
│    - Authors STRICTLY BEHAVIOR-LEVEL specifications (inputs, outputs,  │
│      business invariants, validation rules, edge cases).               │
│    - MUST NOT specify internal class/method structures or legacy idioms│
│    - Prepares black-box unit/feature characterization tests.           │
│    - PRODUCES: Behavioral spec + Target DTOs + Black-box tests        │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ 2. ROLE: REVIEWER (Dedicated Independent Session)                      │
│    - Operates in a separate review session before sandbox delivery.    │
│    - Scrubs specification of any legacy code snippets, comments, or    │
│      method-signature leakage from FreeAmir/upstream heritage.         │
│    - Validates that spec is purely functional/behavioral.              │
│    - Enforces that no mapping tables (RESTRICTED_UPSTREAM_MAPPING) or  │
│      upstream files enter the sandbox.                                 │
│    - Formally signs off scrubbed spec for fresh sandbox deployment.    │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ 3. ROLE: IMPLEMENTER (Isolated Fresh Session in Sandbox)               │
│    - Sandbox: C:\Sandbox\dakhl-cleanroom-core                          │
│    - Context contains ONLY:                                            │
│        (a) Approved behavioral specification                           │
│        (b) Public interface contracts & DTOs                           │
│        (c) Black-box tests                                             │
│    - ZERO access to upstream mirror, old codebase, or git history.     │
│    - Writes clean-room implementation until all tests pass.            │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ 4. ROLE: VERIFIER                                                      │
│    - Imports implementation from sandbox into Dakhl codebase.          │
│    - Runs Dual-Measure Similarity Scanner: MUST BE < 20% on M1 and M2. │
│    - Runs full test suite on SQLite AND MariaDB dakhl_test.            │
│    - Verifies all 7 core accounting invariants.                        │
│    - Records clean-room metadata in LEDGER.csv and LOG.md.             │
└────────────────────────────────────────────────────────────────────────┘
```

### Sandbox Directory Setup
The isolated sandbox repository will be configured at:
```
C:\Sandbox\dakhl-cleanroom-core/
├── contracts/        # Clean public PHP interfaces and DTOs
├── specs/            # Sanitized behavioral specifications
├── tests/            # Black-box characterization tests
├── src/              # Target implementation directory (written by Implementer)
└── composer.json     # Minimal PHP runtime (PHP 8.4, ext-sodium, test framework)
```

---

## 7. Implementation Sequence, Effort Estimation & Checkpoints

The core rewrite is divided into 5 sequential waves designed to preserve dependency order over an **8–10 week** timeline (**40–50 working days**):

```mermaid
flowchart TD
    W1["Wave 1: Hierarchy & Numbering Engine<br/>SubjectService, DocumentNumberService<br/>(5–7 days, Weeks 1–2)"] --> W2
    W2["Wave 2: Journal Voucher Posting Core<br/>DocumentService, DocumentReversalService, PeriodLockGuard<br/>(8–10 days, Weeks 2–4)"] --> W3
    W3["Wave 3: Commercial & Valuation Builders<br/>InvoiceTransactionBuilder, CostOfGoodsService, AncillaryCostService<br/>(10–12 days, Weeks 4–6)"] --> W4
    W4["Wave 4: Treasury, Payments & Cheques<br/>PaymentService, SettlementService, ChequeService<br/>(10–12 days, Weeks 6–8)"] --> W5
    W5["Wave 5: Fiscal Closing & Fresh Baseline Integration<br/>FiscalYearService, Baseline Migrations, Dual Scans & MariaDB Hardening<br/>(7–9 days, Weeks 8–10)"]
```

| Wave | Target Core Units | Key Deliverables & Structured Checkpoints | Estimated Effort Range |
|---|---|---|:---:|
| **Wave 1** | `SubjectService`, `DocumentNumberService` | - Chart of Accounts 3-tier hierarchy engine (cyclic link guard, balance calculation).<br/>- Concurrency-safe atomic document numbering leases with device reservation.<br/>- **Checkpoint 1 (Week 2):** COA tree navigation tests, numbering race-condition collision tests pass on both DB engines. | 5 – 7 Days<br/>*(Weeks 1–2)* |
| **Wave 2** | `DocumentService`, `DocumentReversalService`, `PeriodLockGuard` | - Universal journal voucher posting choke-point.<br/>- $\sum \text{debit} = \sum \text{credit}$ balance invariant enforcement & zero-amount rejection.<br/>- Audit reversal engine (D-127) and 5-state fiscal freeze state machine (D-128).<br/>- **Checkpoint 2 (Week 4):** Zero-unbalanced-lines test suite, reversal voucher tests, period lock gate enforcement pass. | 8 – 10 Days<br/>*(Weeks 2–4)* |
| **Wave 3** | `InvoiceTransactionBuilder`, `InvoiceService`, `CostOfGoodsService`, `AncillaryCostService`, `AncillaryCostTransactionBuilder`, `FixedAssetService`, `GroupActionService` | - Multi-line commercial invoice journal builder (sales, purchases, returns, tax).<br/>- Perpetual inventory COGS valuation and weighted average unit costs.<br/>- Financial mutation boundary gates (D-086, D-087, D-088, D-089).<br/>- **Checkpoint 3 (Week 6):** Invoice status transition tests, COGS valuation tests, mutation immutability guards pass. | 10 – 12 Days<br/>*(Weeks 4–6)* |
| **Wave 4** | `PaymentService`, `SettlementService`, `ChequeService` | - Cash/bank payment vouchers with financial event UUIDs (D-078, D-079).<br/>- Payment allocation and capacity checks (D-081).<br/>- 8-state independent cheque instrument lifecycle (D-070).<br/>- Posted payment immutability (D-084).<br/>- **Checkpoint 4 (Week 8):** Payment allocation isolation tests, cheque lifecycle state transition tests pass. | 10 – 12 Days<br/>*(Weeks 6–8)* |
| **Wave 5** | `FiscalYearService`, Baseline Schema Migrations, Verification & Hardening | - Fiscal closing (P&L transfer, closing/opening vouchers).<br/>- Provenance tracking (`NATIVE` vs `BASELINE`, D-085).<br/>- Fresh Dakhl baseline schema migrations deployed on SQLite & MariaDB `dakhl_test`.<br/>- **Checkpoint 5 (Week 10):** Dual-measure similarity scans $< 15\%$ across all 16 units; full test suite (2,180+ tests) 100% green on SQLite & MariaDB `dakhl_test`. | 7 – 9 Days<br/>*(Weeks 8–10)* |

**Total Estimated Implementation Duration:** **40 – 50 Working Days (8 – 10 Weeks)** (incorporating sandbox spec authoring, reviewer scrubbing, clean-room implementation, dual-measure scans, and SQLite + MariaDB verification checkpoints).

---

## 8. Verification & Owner Approval Gate

Execution of any wave, schema change, or code rewrite will not begin until the Owner formally reviews and approves this design document.

**Owner Sign-Off Checklist:**
- [x] Strategic path confirmed: Option B (Core rewrite + new UX + new schema baseline) (Decision D-130).
- [x] List of 16 protected-core files defined and quarantined.
- [x] 7 core accounting invariants approved.
- [x] Five Schema ADRs (A: Dual Columns [D-131], B: Precision/Rounding [D-132], C: Key Strategy [D-133], D: ON DELETE RESTRICT [D-134], E: DB Immutability Triggers [D-135]) owner-approved.
- [ ] Fresh-session sandbox protocol & behavioral spec rules approved.
- [ ] Wave sequencing, checkpoint criteria, and 8–10 week (40–50 working day) timeline approved.
