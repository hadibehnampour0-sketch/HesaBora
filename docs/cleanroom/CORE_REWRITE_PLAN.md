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

### 5.2 Mapping Table: Old Upstream Schema $\to$ New Dakhl Baseline

| Old Upstream Table / Column | New Dakhl Baseline Table / Column | Type / Constraints | Design Rationale & Accounting Invariant |
|---|---|---|---|
| `documents.id` | `accounting_vouchers.id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Internal primary key. |
| `documents.number` | `accounting_vouchers.voucher_number` | `BIGINT UNSIGNED, UNIQUE(company, year, number)` | Sequential voucher number; immutable once posted. |
| `documents.date` | `accounting_vouchers.voucher_date` | `DATE NOT NULL` | Gregorian posting date. |
| `documents.title` | `accounting_vouchers.description` | `VARCHAR(255) NULL` | Voucher header narrative / memo. |
| `documents.status` | `accounting_vouchers.status` | `ENUM('draft', 'approved', 'reversed')` | Document lifecycle state machine. |
| `documents.type` | `accounting_vouchers.voucher_type` | `VARCHAR(50) NOT NULL DEFAULT 'general'` | Classification: general, invoice, payment, closing. |
| `documents.provenance` | `accounting_vouchers.provenance` | `ENUM('native', 'baseline', 'reversal')` | Audit trail origin tracking (D-085). |
| `documents.uuid` | `accounting_vouchers.uuid` | `CHAR(36) UNIQUE NOT NULL` | Sync aggregate root identifier (D-071, D-073). |
| `transactions.id` | `voucher_lines.id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Line item primary key. |
| `transactions.document_id` | `voucher_lines.voucher_id` | `FOREIGN KEY $\to$ accounting_vouchers(id) ON DELETE RESTRICT` | Parent voucher reference; deletion blocked. |
| `transactions.subject_id` | `voucher_lines.account_id` | `FOREIGN KEY $\to$ accounts(id) ON DELETE RESTRICT` | Target Chart of Accounts node. |
| `transactions.value` | `voucher_lines.debit_amount` / `credit_amount` | `DECIMAL(18, 4) DEFAULT 0.0000` | Replaces signed float with explicit non-negative debit and credit amounts. |
| `transactions.desc` | `voucher_lines.line_memo` | `TEXT NULL` | Detailed item description. |
| `subjects.id` | `accounts.id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Chart of Accounts primary key. |
| `subjects.code` | `accounts.account_code` | `VARCHAR(32) NOT NULL` | Structured hierarchical code (e.g. `011001001`). |
| `subjects.name` | `accounts.title` | `VARCHAR(255) NOT NULL` | Standard account title. |
| `subjects.parent_id` | `accounts.parent_id` | `FOREIGN KEY $\to$ accounts(id) ON DELETE RESTRICT` | Recursive parent account reference. |
| `subjects.type` | `accounts.financial_nature` | `ENUM('debtor', 'creditor', 'both')` | Balance nature (بدهکار / بستانکار). |
| `subjects.is_permanent` | `accounts.is_permanent` | `BOOLEAN NOT NULL DEFAULT TRUE` | Balance sheet permanent vs temporary income statement. |
| `invoices.id` | `invoices.id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Commercial invoice primary key. |
| `invoices.number` | `invoices.invoice_number` | `BIGINT UNSIGNED, UNIQUE(company, type, number)` | Official sequence number (D-075). |
| `invoices.status` | `invoices.status` | `ENUM('draft', 'approved', 'rejected', 'paid')` | Status workflow (D-076, D-086–D-089). |
| `payments.id` | `treasury_payments.id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Treasury cash/bank receipt & payment primary key. |
| `payments.uuid` | `treasury_payments.financial_event_uuid` | `CHAR(36) UNIQUE NOT NULL` | Canonical economic event UUID (D-078/D-079). |
| `payment_allocations.id` | `payment_allocations.id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Allocation primary key. |
| `cheques.id` | `cheque_instruments.id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Commercial paper primary key (D-070). |
| `cheques.status` | `cheque_instruments.status` | `ENUM(8 states)` | 8-state independent cheque lifecycle (D-070). |

---

## 5.3 Mermaid Schema Relationship Diagram

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
│    - Authors behavioral specification in docs/cleanroom/SPECS/.        │
│    - Prepares black-box unit/feature characterization tests.           │
│    - PRODUCES: Functional spec + Interface contracts + Black-box tests│
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ 2. ROLE: REVIEWER                                                      │
│    - Scrubs specification of any FreeAmir code snippets or comments.   │
│    - Verifies no tainted symbols, line lengths, or idioms exist.       │
│    - Signs off spec for fresh sandbox deployment.                      │
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

The core rewrite is divided into 5 sequential waves designed to preserve dependency order:

```mermaid
flowchart TD
    W1["Wave 1: Foundation & Hierarchy<br/>SubjectService, DocumentNumberService<br/>(3 days)"] --> W2
    W2["Wave 2: Journal Voucher Posting Core<br/>DocumentService, DocumentReversalService, PeriodLockGuard<br/>(5 days)"] --> W3
    W3["Wave 3: Commercial & Valuation Builders<br/>InvoiceTransactionBuilder, CostOfGoodsService, AncillaryCostService<br/>(6 days)"] --> W4
    W4["Wave 4: Treasury, Payments & Cheques<br/>PaymentService, SettlementService, ChequeService<br/>(6 days)"] --> W5
    W5["Wave 5: Fiscal Closing & Baseline Schema<br/>FiscalYearService, New Baseline Migrations, Invariant Verification<br/>(5 days)"]
```

| Wave | Target Core Units | Key Deliverables & Checkpoints | Estimated Effort |
|---|---|---|:---:|
| **Wave 1** | `SubjectService`, `DocumentNumberService` | - Chart of Accounts 3-tier hierarchy engine.<br/>- Concurrency-safe atomic document numbering leases.<br/>- Checkpoint: COA tree tests & numbering collision tests pass. | 3 Days |
| **Wave 2** | `DocumentService`, `DocumentReversalService`, `PeriodLockGuard` | - Universal journal voucher posting choke-point.<br/>- $\sum \text{value} = 0$ balance invariant enforcement.<br/>- Audit reversal engine (D-127) and 5-state fiscal freeze (D-128).<br/>- Checkpoint: Balanced document tests, reversal tests, lock guards pass. | 5 Days |
| **Wave 3** | `InvoiceTransactionBuilder`, `InvoiceService`, `CostOfGoodsService`, `AncillaryCostService`, `AncillaryCostTransactionBuilder`, `FixedAssetService`, `GroupActionService` | - Multi-line invoice journal builder (sales, purchases, returns, tax).<br/>- Perpetual inventory COGS valuation and average unit costs.<br/>- Financial mutation boundary gates (D-086–D-089).<br/>- Checkpoint: Invoice posting tests, COGS calculation tests pass. | 6 Days |
| **Wave 4** | `PaymentService`, `SettlementService`, `ChequeService` | - Cash/bank payment vouchers with financial event UUIDs (D-078/D-079).<br/>- Payment allocation and capacity checks (D-081).<br/>- 8-state independent cheque instrument lifecycle (D-070).<br/>- Posted payment immutability (D-084).<br/>- Checkpoint: Payment allocation tests, cheque state transition tests pass. | 6 Days |
| **Wave 5** | `FiscalYearService`, New Baseline Migrations, Integration Hardening | - Fiscal closing (P&L transfer, closing/opening vouchers).<br/>- Provenance tracking (`NATIVE` vs `BASELINE`, D-085).<br/>- Fresh Dakhl baseline schema migrations deployed on `dakhl_test`.<br/>- Checkpoint: Dual-measure scans $< 20\%$ across all 16 units; full suite passes on SQLite & MariaDB. | 5 Days |

**Total Estimated Implementation Duration:** 25 Working Days.

---

## 8. Verification & Owner Approval Gate

Execution of any wave, schema change, or code rewrite will not begin until the Owner formally reviews and approves this design document.

**Owner Sign-Off Checklist:**
- [x] Strategic path confirmed: Option B (Core rewrite + new UX + new schema baseline).
- [ ] List of 16 protected-core files approved.
- [ ] 7 core accounting invariants approved.
- [ ] New baseline schema proposal and old $\to$ new mapping approved.
- [ ] Fresh-session sandbox protocol approved.
- [ ] Wave sequencing and checkpoints approved.
