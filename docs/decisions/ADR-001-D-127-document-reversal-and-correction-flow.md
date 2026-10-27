# ADR-001 / Decision D-127: Reversal & Correction Architecture for Immutable Accounting Documents
**Title:** Reversal and Correction Flow (سند ابطال و اصلاح سند حسابداری)  
**Status:** APPROVED & LOCKED  
**Date:** 2026-10-27  
**Deciders:** Chief Systems Architect, Senior Accountant, Senior Backend Engineer  
**Context:** Super Prompt r15/r16 Phase E, Master Context §25.5, Iranian Commercial Code (قانون تجارت)

---

## 1. CONTEXT & PROBLEM STATEMENT

Under Decisions D-084, D-086, D-087, D-088, and D-089, Dakhl strictly enforces financial immutability:
- Approved accounting documents (`documents`), posted transactions (`transactions`), payments (`payments`), and allocations (`payment_allocations`) cannot be edited, hard-deleted, or destructively mutated through the service layer.
- While this immutability protects general ledger integrity (`SUM(transactions.value) = 0`) and multi-tenant isolation, real-world bookkeeping requires correcting human errors (e.g., selecting the wrong account code, transposition typos, incorrect debit/credit values, or duplicate postings).
- Previously, no formal reversal mechanism existed, leaving accountants without an auditable path to correct mistakes without corrupting historical records.

---

## 2. DECISION PRINCIPLES & CONTRACT

### 2.1 The Mirror Document Principle (سند معکوس/ابطال)
1. **Never Mutate Historical Ledger:** An approved document and its transactions are never deleted or altered.
2. **Atomic Mirror Posting:** Reversal is achieved by creating an entirely new, approved `Document` whose transactions mirror the original document's transactions with inverted signs (`value = -1 * original.value`).
3. **Double-Entry Invariant:**
   $$\sum \text{original.transactions.value} = 0$$
   $$\sum \text{reversal.transactions.value} = -1 \times 0 = 0$$
   $$\sum (\text{original} + \text{reversal}) = 0$$
   The net financial effect across all involved accounts is brought back to exactly zero in an auditable manner.

### 2.2 Traceability & Linkage Schema
The `documents` table is augmented with:
- `reverses_document_id` (nullable foreignId constrained to `documents.id` nullOnDelete): Set on the **reversal document**, pointing back to the original document.
- `reversed_at` (nullable timestamp): Set on the **original document** when a valid reversal is committed.
- `reversed_by_id` (nullable foreignId constrained to `users.id` nullOnDelete): Set on the **original document** recording who ordered the reversal.
- `reversal_reason_code` (nullable string): Standardized enum/code (`clerical_error`, `wrong_account`, `incorrect_amount`, `duplicate_entry`, `voided_invoice`, `other`).
- `reversal_reason` (nullable text): Free-text explanation entered by the user.

### 2.3 Strict Invariants & Collision Guards
- **Double-Reversal Prohibited:** A document with `reversed_at !== null` or where `reverses_document_id` already matches cannot be reversed again.
- **Reversing a Reversal Prohibited:** A document with `reverses_document_id !== null` is a reversal document and cannot be reversed directly.
- **Drafts Cannot Be Reversed:** Only approved documents (`approved_at !== null`) can be reversed. Unapproved drafts are simply edited or deleted directly.
- **Concurrency Locking:** Reversal executes inside `DB::transaction()` with `Document::where('id', $id)->lockForUpdate()->firstOrFail()`, preventing concurrent dual-reversals.

### 2.4 "Correct This" Flow (اصلاح سند - Clerk Ergonomics)
For everyday accounting clerks (especially in Simple UI Mode):
1. The clerk clicks "اصلاح سند" (Correct This) and enters a reason.
2. The system executes `reverseDocument()`, generating and approving the reversal document.
3. The system simultaneously generates a **new unapproved draft document** containing the exact accounts, values, and line descriptions of the original document.
4. The clerk is redirected to edit the new draft, modify the errant line or amount, and submit it for normal review/approval.
5. **Segregation of Duties:** The clerk creates the correction draft, but approval requires authorized credentials (`documents.approve` / `documents.change-status`).

### 2.5 Period Close Integration (Phase F Alignment)
- Inside a **Locked Period**: Reversal is strictly rejected. A correction entry must be dated in the current open period.
- Inside a **Closed Period**: Reversal requires elevated permission (`documents.reverse.closed-period`) and explicit audit logging.
- Inside an **Open Period**: Reversal proceeds with `documents.reverse` permission.

### 2.6 Outbox / Offline Sync Parity
- Reversal is represented as a first-class idempotent operation (`SyncOperation::REVERSE` or atomic command payload with `operation_id`).
- When a client issues a reversal, the server verifies preconditions and records the reversal deterministically.

---

## 3. CONSEQUENCES & BENEFITS
- **Auditor Compliance:** Iranian tax auditors (ممیزین مالیاتی) require full traceability; reversed documents display "ابطال شده با سند شماره X" with reasons, dates, and operator identity.
- **Zero Ledger Corruption:** Account balances (`SubjectService::sumSubject()`) reflect genuine debits and credits without orphaned postings.
- **Clerk Speed:** Reduces time to fix invoice or journal typos from manual re-entry to a single guided workflow.
