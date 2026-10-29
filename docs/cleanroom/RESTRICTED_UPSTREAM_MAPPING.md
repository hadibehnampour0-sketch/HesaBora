# RESTRICTED ARCHITECT-ONLY REFERENCE: Upstream Schema Mapping Table

> [!CAUTION]
> **QUARANTINED CLEAN-ROOM GOVERNANCE MATERIAL**  
> This document contains direct structural mappings derived from the legacy upstream database schema.  
> **STRICT SECURITY RULE:** Under no circumstances may this file, its contents, or its column-to-column mappings be copied, exposed, mounted, or provided to any clean-room Implementer session or sandbox environment (`C:\Sandbox\dakhl-cleanroom-core`).  
> The Implementer must work **EXCLUSIVELY** from the target Dakhl specification, the new schema baseline, and black-box tests.

---

## 1. Upstream Legacy Schema to New Dakhl Baseline Structural Mapping

| Legacy Upstream Table / Column | Target Dakhl Schema Table / Column | Target Data Type & Constraints | Architectural Purpose & Invariant Context |
|---|---|---|---|
| `documents.id` | `accounting_vouchers.id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Internal database surrogate key. |
| `documents.uuid` | `accounting_vouchers.uuid` | `CHAR(36) UNIQUE NOT NULL` | Sync aggregate root identifier (D-071, D-073). |
| `documents.number` | `accounting_vouchers.voucher_number` | `BIGINT UNSIGNED NOT NULL, UNIQUE(company_id, fiscal_period_id, voucher_number)` | Sequential document number; strictly immutable once posted. |
| `documents.date` | `accounting_vouchers.voucher_date` | `DATE NOT NULL` | Financial posting date (Gregorian storage). |
| `documents.title` | `accounting_vouchers.description` | `VARCHAR(255) NULL` | Accounting memo / document narrative. |
| `documents.status` | `accounting_vouchers.status` | `ENUM('draft', 'approved', 'reversed')` | Voucher lifecycle state machine. |
| `documents.type` | `accounting_vouchers.voucher_type` | `VARCHAR(50) NOT NULL DEFAULT 'general'` | Classification (general, invoice, payment, closing). |
| `documents.provenance` | `accounting_vouchers.provenance` | `ENUM('native', 'baseline', 'reversal')` | Audit trail origin tracking (D-085, D-127). |
| `documents.reversed_document_id` | `accounting_vouchers.reversed_voucher_id` | `FOREIGN KEY -> accounting_vouchers(id) NULL` | Link to reversed original voucher (D-127). |
| `transactions.id` | `voucher_lines.id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Line item primary key. |
| `transactions.document_id` | `voucher_lines.voucher_id` | `FOREIGN KEY -> accounting_vouchers(id) ON DELETE RESTRICT` | Parent voucher reference; hard deletion forbidden. |
| `transactions.subject_id` | `voucher_lines.account_id` | `FOREIGN KEY -> accounts(id) ON DELETE RESTRICT` | Target Chart of Accounts node. |
| `transactions.value` | `voucher_lines.debit_amount` / `credit_amount` | `DECIMAL(18, 4) UNSIGNED DEFAULT 0.0000` | Replaces signed float with explicit debit and credit amounts. |
| `transactions.desc` | `voucher_lines.line_memo` | `TEXT NULL` | Item description / line-level narrative. |
| `subjects.id` | `accounts.id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Chart of Accounts primary key. |
| `subjects.code` | `accounts.account_code` | `VARCHAR(32) NOT NULL` | Structured hierarchical code (e.g. `011001001`). |
| `subjects.name` | `accounts.title` | `VARCHAR(255) NOT NULL` | Persian account title. |
| `subjects.parent_id` | `accounts.parent_id` | `FOREIGN KEY -> accounts(id) ON DELETE RESTRICT` | Recursive parent account reference. |
| `subjects.type` | `accounts.financial_nature` | `ENUM('debtor', 'creditor', 'both')` | Account normal balance nature (بدهکار / بستانکار). |
| `subjects.is_permanent` | `accounts.is_permanent` | `BOOLEAN NOT NULL DEFAULT TRUE` | Permanent balance sheet vs temporary nominal accounts. |
| `invoices.id` | `invoices.id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Commercial invoice primary key. |
| `invoices.uuid` | `invoices.uuid` | `CHAR(36) UNIQUE NOT NULL` | Offline/online synchronization UUID. |
| `invoices.number` | `invoices.invoice_number` | `BIGINT UNSIGNED NOT NULL, UNIQUE(company_id, invoice_type, invoice_number)` | Official sequential commercial invoice number (D-075). |
| `invoices.status` | `invoices.status` | `ENUM('draft', 'approved', 'rejected', 'paid')` | Commercial invoice lifecycle (D-076, D-086–D-089). |
| `invoices.customer_id` | `invoices.counterparty_id` | `FOREIGN KEY -> counterparties(id) ON DELETE RESTRICT` | Buyer / seller counterparty relation. |
| `payments.id` | `treasury_payments.id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Treasury cash/bank receipt & payment primary key. |
| `payments.uuid` | `treasury_payments.financial_event_uuid` | `CHAR(36) UNIQUE NOT NULL` | Canonical economic event UUID (D-078/D-079). |
| `payment_allocations.id` | `payment_allocations.id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Settlement allocation primary key. |
| `payment_allocations.uuid` | `payment_allocations.uuid` | `CHAR(36) UNIQUE NOT NULL` | Allocation synchronization UUID (D-081). |
| `cheques.id` | `cheque_instruments.id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Commercial paper primary key (D-070). |
| `cheques.status` | `cheque_instruments.status` | `ENUM(8 states)` | 8-state independent cheque lifecycle (D-070). |
