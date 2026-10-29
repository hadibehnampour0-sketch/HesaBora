# CLEAN-ROOM BEHAVIORAL SPECIFICATION: DOCUMENT IMPORT & EXPORT SERVICE

**Target Directory:** `app/Services/DocumentImportExport/`  
**Clean-Room Wave:** Wave 2 (Standalone Data Migration & File Interchange)  
**Author / Role:** Software Architect & Senior Accountant (Analyst & Reviewer)  
**Date:** 2026-10-29  
**Specification Status:** APPROVED FOR CLEAN-ROOM IMPLEMENTATION

---

## 1. OBJECTIVE & SCOPE

The Document Import/Export subsystem enables bidirectional data portability of double-entry accounting records (اسناد حسابداری) between Dakhl and external formats (CSV and tabular text).

### Non-Negotiable Accounting & System Invariants:
1. **Double-Entry Balance Invariant:** Every imported document must strictly satisfy:
   $$\sum \text{Debit} = \sum \text{Credit}$$
   Unbalanced documents must be rejected with an explanatory error; partial documents must never be persisted.
2. **Transactional Atomicity:** All documents and transactions within an import batch are committed inside `DB::transaction()`. If an unrecoverable parse or validation error occurs, the entire batch rolls back.
3. **Multi-Tenant / Company Isolation:** Imported documents and generated Chart of Accounts subjects are bound strictly to `active-company-id` / current fiscal year. Cross-company contamination is strictly prevented.
4. **Idempotency:** Re-importing the same balanced file must be idempotent; existing documents matching `(number, date)` should be skipped or updated cleanly without duplicate transaction lines.

---

## 2. FORMAT ARCHITECTURE & CATALOGUE

### 2.1 Abstract Format Base (`DocumentImportFormat`)
Every import format subclass must provide:
- `key(): string`: Machine-readable key (`standard`, `parsian`, with `free_amir` alias for compatibility).
- `label(): string`: Human-readable localized label.
- `matches(array $headers): bool`: Fast heuristic detection based on normalized header row.
- `import(array $rows, User $user): array`: Executes parsing, subject resolution, and document persistence, returning:
  ```php
  array{
      subjects_created: int,
      subjects_skipped: int,
      documents_created: int,
      documents_skipped: int,
      rows_skipped: int,
      errors: array<int, string>
  }
  ```

### 2.2 Standard Accounting Format (`DakhlStandardImportFormat`)
Canonical interchange schema with 14 columns:
1. `doc_number`: Integer / numeric string.
2. `doc_date`: Jalali (`YYYY/MM/DD`) or Gregorian (`YYYY-MM-DD`).
3. `doc_title`: String document description.
4. `doc_type`: Accounting document type (default: general).
5. `doc_status`: Approved vs. unapproved.
6. `subject_root_code`: Top-level group code (کل).
7. `subject_moein_code`: Subsidiary account code (معین).
8. `subject_tafsili_code`: Detail ledger code (تفصیلی).
9. `subject_name`: Account title.
10. `subject_type`: Asset, Liability, Equity, Revenue, Expense (`both`, `debtor`, `creditor`).
11. `subject_is_permanent`: Boolean flag indicating balance sheet permanence.
12. `transaction_desc`: Line item memo.
13. `debit`: Monetary amount (numeric).
14. `credit`: Monetary amount (numeric).

Header normalization must match Persian synonyms:
- `شماره سند` -> `doc_number`
- `تاریخ سند` -> `doc_date`
- `عنوان سند` -> `doc_title`
- `کد ریشه` / `کد سرفصل اصلی` -> `subject_root_code`
- `کد معین` -> `subject_moein_code`
- `کد تفصیلی` -> `subject_tafsili_code`
- `نام سرفصل` -> `subject_name`
- `بدهکار` -> `debit`
- `بستانکار` -> `credit`

### 2.3 Parsian Migration Format (`ParsianImportFormat`)
Parsian software produces transaction ledgers and trial balances.
- Root subjects table: Pre-defined 3-digit groups (`010` Cash/صندوق, `011` Bank/بانک, `012` Receivables, `015` Inventory, `023` Payables, `040` Revenue, `050` Expense, etc.).
- Multi-tier subject derivation: When transaction records contain compound codes (e.g. `010001001`), extracts root (`010`), moein (`001`), and tafsili (`001`) with automatic parent-child hierarchy binding.

---

## 3. SUBJECT HIERARCHY RESOLUTION ENGINE (`ImportSubjectResolver`)

1. **Resolution Strategy:**
   - Looks up existing subjects by `code` under `company_id`.
   - If not found by code, falls back to `name` match under same `parent_id` (unless the name appears ambiguously for multiple codes in the same file).
2. **Missing Ancestor Synthesis:**
   - If a leaf account references parent or root codes not yet present in the database, the resolver auto-creates the missing intermediate nodes.
   - Leaf nodes inherit permanence (`is_permanent`) and financial nature (`type`) from parent or explicit row attributes.
3. **In-Memory Cache:**
   - Caches resolved Subject instances by code to avoid redundant queries during large multi-thousand-row imports.

---

## 4. EXPORT ENGINE REQUIREMENTS

1. **Query Filtering:**
   - Range filtering: `start_document_number`, `end_document_number`, `start_date`, `end_date`.
   - Status filtering: `approved`, `unapproved`, `all`.
   - Text search: Matches `title` or transaction `desc`.
2. **Output Stream:**
   - Generates UTF-8 encoded CSV stream with Byte Order Mark (`BOM` = `\xEF\xBB\xBF`) for Excel Persian compatibility.
   - Mandatory columns are always included regardless of user custom column selection.
   - Column ordering follows canonical schema.
