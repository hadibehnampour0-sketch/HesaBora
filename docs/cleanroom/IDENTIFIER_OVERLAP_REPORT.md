# Clean-Room Identifier Overlap & Lexical Independence Audit Report
**Classification:** RESTRICTED — FOR INDEPENDENT REVIEWER SESSION ONLY  
**Sandbox Target:** `C:\Sandbox\dakhl-cleanroom-core\`  
**Upstream Reference:** FreeAmir Heritage Baseline (Classified in `LEDGER.csv`)  
**Status:** AUDITED & VERIFIED  

---

## 1. Executive Summary
This report analyzes all naming identifiers (database tables, column names, public contract method names, and domain symbols) currently present in the Dakhl Clean-Room Sandbox (`C:\Sandbox\dakhl-cleanroom-core\`) against the legacy upstream repository.

The goal of this audit is to provide the independent Reviewer with conclusive proof that:
1. All identifier overlap is strictly confined to universal double-entry accounting and Iranian statutory commercial terminology.
2. The core schema and contracts reflect the newly approved Dakhl Architectural Decisions (ADR-A through ADR-E / Decisions D-131 through D-135), rather than legacy database structures.
3. Zero proprietary, idiosyncratic, or tainted identifiers from the upstream codebase exist in the clean-room sandbox.

---

## 2. Table-Level Identifier Analysis

| Clean-Room Table Name | Upstream Table Equivalent | Coincidence Classification | Architectural Distinction / Justification |
| :--- | :--- | :--- | :--- |
| `accounts` | `accounts` | Standard Terminology | Universal accounting concept (کدینگ حساب‌ها). Clean-room schema enforces strict 3-tier hierarchy (`account_tier`, `account_nature`) with UUIDv7 PK/index. |
| `journal_vouchers` | `documents` | **REDESIGNED (Clean-Room)** | Completely renamed from `documents`. Explicitly models standard double-entry journal vouchers (سند حسابداری) with `voucher_number`, `provenance`, and `reversed_voucher_id`. |
| `journal_voucher_lines` | `transactions` | **REDESIGNED (Clean-Room)** | Completely renamed from `transactions`. Eliminates legacy signed `value` column; introduces explicit `debit_amount` and `credit_amount` columns (ADR-A / D-131). |
| `invoices` | `invoices` | Standard Terminology | Universal Iranian commercial invoice entity. Clean-room schema introduces strict `invoice_type` integer enum, `status` lifecycle, and financial activity mutation boundaries (D-086..D-089). |
| `invoice_items` | `invoice_items` | Standard Terminology | Universal commercial line item relation. Enforces whole-Rial money and 6-decimal quantity precision (ADR-B / D-132). |
| `payments` | `payments` | Standard Terminology | Standard treasury entity. Encapsulates atomic economic event with canonical UUIDv7 (D-078 / D-080) and unallocated balance tracking. |
| `payment_allocations` | `payment_allocations` | Architectural Entity | Independent settlement entity introduced in Dakhl Phase 2.8D (Decision D-081) with canonical UUID. |
| `cheques` | `cheques` | Standard Terminology | Dedicated Iranian commercial banking instrument modeling the 8-state Sayad lifecycle (Decision D-070). |

---

## 3. Column-Level Identifier Analysis

### 3.1 Universal Accounting Terminology (Legitimate Overlap)
The following column names coincide between legacy and clean-room schemas because they represent indispensable statutory accounting concepts in both Persian and international standards:
- `id`, `created_at`, `updated_at`: Standard relational metadata.
- `company_id`: Multi-tenant boundary identifier.
- `code`, `title`: Account classification attributes.
- `description`: Voucher and line narrative string.
- `amount`, `subtotal_amount`, `discount_amount`, `tax_amount`, `total_amount`: Universal commercial monetary totals.
- `due_date`, `sayad_number`, `bank_name`: Commercial paper / cheque attributes mandated by the Central Bank of Iran (CBI / بانک مرکزی).

### 3.2 Deliberate Clean-Room Architectural Divergences
The clean-room schema intentionally departs from upstream quirks to satisfy ADR-A through ADR-E:

| Architectural Domain | Upstream Legacy Mechanism | Dakhl Clean-Room Implementation | Rationale |
| :--- | :--- | :--- | :--- |
| **Money Representation** | Inconsistent integer / float Rials | Whole `BIGINT` Rials with `ROUND_HALF_UP` math (ADR-B / D-132) | Eliminates rounding discrepancies; aligns with Moadian tax requirements. |
| **Double-Entry Representation** | Single signed column `transactions.value` (- for credit, + for debit) | Dual columns: `debit_amount BIGINT` and `credit_amount BIGINT` (ADR-A / D-131) | Eliminates ambiguity; enforces DB CHECK constraints: `(debit > 0 AND credit = 0) OR (debit = 0 AND credit > 0)`. |
| **Row Immutability** | Application-level checks | DB triggers `trg_prevent_posted_voucher_update` and `trg_prevent_posted_voucher_delete` (ADR-E / D-135) | Defense-in-depth across SQLite and MariaDB. |
| **Document State Machine** | Implicit draft boolean | Explicit 5-state fiscal period lifecycle (D-128) + 3-state voucher lifecycle | Formal audit state machine. |
| **Reversals** | Destructive deletion / voiding | Immutable mirror reversal vouchers (`reversed_voucher_id`, `is_reversal = 1`) (D-127) | Complies with Iranian accounting standards. |
| **Provenance Tracking** | Ad-hoc `is_copied` flag | Record-level `provenance` (`1 = NATIVE`, `2 = BASELINE`) (D-085) | Protects multi-year audit history and gates company deletion. |
| **Identifier Strategy** | Raw auto-increment ID only | Auto-increment PK + `BINARY(16)` canonical UUIDv7 with UNIQUE index (ADR-C / D-133) | Offline distributed sync readiness without exposing internal sequence numbers. |

---

## 4. Proprietary Upstream Identifier Scan
An automated scan of all text, schema, contract, specification, and test files in `C:\Sandbox\dakhl-cleanroom-core\` was conducted against distinctive upstream tokens:
- `FreeAmir`: **0 occurrences found**
- `freeamir`: **0 occurrences found**
- `amir-accounting`: **0 occurrences found**
- `amir.cust_subject`: **0 occurrences found**
- `amir.bank_parent`: **0 occurrences found**
- `upstream_hash`: **0 occurrences found**
- `parse_csv_parsian`: **0 occurrences found**
- `sanad_tarazi`: **0 occurrences found**
- `legacy_schema_mapping`: **0 occurrences found**

---

## 5. Reviewer Checklist & Recommendation
- [x] **No Proprietary Identifiers:** All identifiers in the sandbox are either universal accounting terms or newly minted Dakhl clean-room names.
- [x] **No Structure Leaks:** Specifications and contracts specify inputs, outputs, invariants, and mathematical rules without referencing legacy class names or internal file paths.
- [x] **ADR Compliance:** Schema DDLs strictly follow ADR-A through ADR-E.
- [x] **Verification:** Verified clean by `verify_sandbox_hygiene.py` v2.0.

**Recommendation:** The sandbox kit naming and schema are fully certified as clean, independent, and clear for Implementer session access.
