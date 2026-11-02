# Dakhl Clean-Room Reviewer Packet & Audit Protocol
**Target Directory:** `C:\Sandbox\dakhl-cleanroom-core\`  
**Role:** Independent Clean-Room Reviewer  
**Access Scope:** RESTRICTED — The Reviewer session has access **ONLY** to `C:\Sandbox\dakhl-cleanroom-core\`. The Reviewer has NO access to the upstream repository, legacy git history, or the main application repo (`c:\Projects\HesaBora\`).

---

## 1. Reviewer Mission & Security Model
As the independent Reviewer, your objective is to audit the clean-room sandbox kit before the Implementer session is allowed to write code. You must certify that the sandbox is completely sanitized, contains no structural or wording leaks from legacy heritage, complies with Dakhl Architectural Decisions (ADR-A through ADR-E), and presents a rigorous fail-first test harness.

---

## 2. Review Checklist

### Check 1: Structure-Leak Audit
Inspect all 8 files in `contracts/` and all 16 files in `specs/`:
- [ ] Confirm there are NO references to legacy class names (e.g., `App\Services\*`, `DocumentService`, `TransactionBuilder`, `InvoiceService`).
- [ ] Confirm all contracts are defined under clean namespace `Dakhl\Cleanroom\Core\Contracts\*`.
- [ ] Confirm interface method signatures specify universal data transfer arrays or scalar types without referencing legacy models (`Eloquent`, `Model`, `InvoiceItem`).
- [ ] Confirm no legacy directory paths or git history exist.

### Check 2: Wording-Leak & Commentary Audit
Inspect the Persian and English text in `specs/SPEC_01` through `SPEC_16`:
- [ ] Verify that all text describes **accounting rules, mathematical equations, inputs, outputs, and invariants** rather than code implementation instructions.
- [ ] Verify no comments or text contain legacy branding (`FreeAmir`, `AmirAccounting`, `amir_*`).
- [ ] Check for distinctive legacy phrasings or translation quirks; all accounting terms must be standard Iranian statutory terms (e.g., سند حسابداری، بدهکار، بستانکار، کدینگ سه سطحی، بهای تمام‌شده، ارزش اسقاط، چک صیادی).

### Check 3: Schema Naming & ADR Compliance
Inspect `schema/ddl_sqlite.sql`, `schema/ddl_mariadb.sql`, and `schema/SCHEMA_SPEC.md`:
- [ ] **ADR-A (D-131):** Verify table `journal_voucher_lines` uses explicit dual columns `debit_amount BIGINT` and `credit_amount BIGINT` with CHECK constraints preventing zero or dual amounts.
- [ ] **ADR-B (D-132):** Verify ledger currency values are stored as whole Rials (`BIGINT`), with inventory quantities defined as `DECIMAL(18, 6)`.
- [ ] **ADR-C (D-133):** Verify all tables have `id INTEGER PRIMARY KEY` + `uuid BLOB/BINARY(16) UNIQUE` (UUIDv7).
- [ ] **ADR-D (D-134):** Verify foreign key constraints on financial tables enforce `ON DELETE RESTRICT`.
- [ ] **ADR-E (D-135):** Verify database triggers enforce immutability on posted vouchers (`status = 2`).

### Check 4: Behavior-Level vs Structure-Level Validation
- [ ] Verify that specifications do not dictate internal class hierarchies, design patterns, or private helper methods.
- [ ] Verify that the Implementer is free to choose internal architectural implementation (classes, services, data mappers) as long as public contracts and tests are satisfied.

### Check 5: Test Suite 100% RED Baseline
Execute the characterization test suite inside the sandbox:
```bash
php vendor/phpunit/phpunit/phpunit -c phpunit.xml
```
Verify the following execution criteria:
- [ ] Total Tests: **62**
- [ ] Failing Tests: **62** (54 missing implementation errors, 8 expected domain exceptions)
- [ ] Passing Tests: **0** (No tests pass vacuously)
- [ ] All tests resolve implementation classes exclusively from `Dakhl\Core\...` namespace which does not exist yet.

### Check 6: Automated Hygiene Verification
Execute the automated hygiene script:
```bash
python verify_sandbox_hygiene.py
```
- [ ] Exit code must be 0 (`Sandbox Status: 100% CLEAN & HYGIENIC`).
- [ ] Zero tainted file matches from `LEDGER.csv`.
- [ ] Zero SHA-256 hash matches against upstream files.
- [ ] Zero forbidden legacy tokens detected.

---

## 3. Reviewer Sign-Off Certification Template

Once all 6 checks are completed and verified, the Reviewer must issue the following formal sign-off block:

```markdown
### Clean-Room Reviewer Sign-Off Certification
- **Reviewer Agent ID:** [Agent-ID / Session-ID]
- **Timestamp:** [YYYY-MM-DD HH:MM:SS UTC]
- **Target Hash / Directory:** `C:\Sandbox\dakhl-cleanroom-core\`
- **Hygiene Verification:** PASSED (Exit code 0, 0 violations)
- **Test Baseline:** VERIFIED (62 tests, 62 failing, 0 passing)
- **ADR Conformance:** CERTIFIED (ADR-A through ADR-E compliant)
- **Status:** APPROVED FOR IMPLEMENTER HANDOFF
```
