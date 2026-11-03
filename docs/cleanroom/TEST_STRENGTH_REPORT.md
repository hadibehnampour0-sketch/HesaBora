# Dakhl Clean-Room Test Suite Strength & Invariant Resistance Report
**Document Version:** 1.0.0  
**Verification Date:** 2026-11-03  
**Evaluator:** Independent Core Verification Gate  
**Location of Stubs:** `C:\Reference\stubs\` (Maintained strictly outside the Implementer Kit)  
**Target Sandbox:** `C:\Sandbox\dakhl-cleanroom-core\`  
**Status:** PASSED (Pass Rate Threshold: <= 5.0% Required; Observed: 0.00%)  

---

## 1. Executive Summary & Methodology

To guarantee that the clean-room specification test suite cannot be satisfied vacuously, superficially, or through fake no-op implementations, two adversarial mock implementations of `CoreKernelContract` were authored outside the sandbox kit (`C:\Reference\stubs\`):

1. **`ThrowingStubKernel`:** Every interface method unconditionally throws a generic `\RuntimeException("ThrowingStub: <method>")`. This tests whether tests fail when encountering unhandled failures and verifies that tests expecting domain exceptions do NOT catch generic runtime exceptions carelessly.
2. **`NeutralStubKernel`:** Every interface method returns neutral/vacuous values (e.g. `0`, `0.0`, `false`, `null`, `[]`, or empty objects) without executing any business logic or mutating state. This tests whether assertions assert specific state changes, calculations, and invariants rather than merely asserting non-null or truthy returns.

Both stubs were executed against the entire clean-room test suite using PHPUnit 11.5 on PHP 8.4.

---

## 2. Empirical Verification Results

| Implementation Stub | Total Specification Tests | Passed Tests | Failed / Errored Tests | Specification Pass Rate | Conformance Status |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **`ThrowingStubKernel`** | 71 | 0 | 71 (37 Errors, 34 Failures) | **0.00%** | **PASSED (<= 5%)** |
| **`NeutralStubKernel`** | 71 | 0 | 71 (0 Errors, 71 Failures) | **0.00%** | **PASSED (<= 5%)** |
| **DDL Conformance (Engine)** | 4 | 4 | 0 | **100.0%** | **PASSED (Green Baseline)** |

> **Key Finding:** Exactly **zero** specification tests pass against either stub implementation. The pass rate for both stubs is **0.00%**, significantly outperforming the required threshold of $\le 5.0\%$.

---

## 3. Specification Domain Breakdown & Resistance Analysis

Every single domain specification contains at least one positive/happy-path characterization test and at least one negative/violation invariant test.

| Domain Specification | Happy-Path Tests | Negative / Violation Tests | Total Spec Tests | Throwing Stub Result | Neutral Stub Result |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **SPEC_01:** Chart of Accounts Hierarchy | 3 | 4 | 7 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **SPEC_02:** Document Numbering Engine | 2 | 3 | 5 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **SPEC_03:** Universal Journal Voucher Posting | 1 | 4 | 5 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **SPEC_04:** Mirror Audit Reversals | 1 | 2 | 3 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **SPEC_05:** Fiscal Period Freeze State Machine | 1 | 3 | 4 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **SPEC_06:** Commercial Invoice Posting | 1 | 2 | 3 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **SPEC_07:** Perpetual Inventory Valuation (COGS) | 2 | 1 | 3 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **SPEC_08:** Ancillary Landed Costs Allocation | 2 | 1 | 3 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **SPEC_09:** Treasury Payments & Receipts | 2 | 3 | 5 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **SPEC_10:** Payment Settlement Allocations | 1 | 3 | 4 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **SPEC_11:** Cheque Instrument Lifecycle | 2 | 2 | 4 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **SPEC_12:** Fixed Assets & Depreciation | 1 | 2 | 3 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **SPEC_13:** Cost Dependency Recalculation | 2 | 1 | 3 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **SPEC_14:** Invoice Mutation Boundaries | 1 | 4 | 5 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **SPEC_15:** Financial Provenance Tracking | 1 | 2 | 3 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **SPEC_16:** Fiscal Closing & Roll-Over | 1 | 2 | 3 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **Cross-Cutting: Concurrency Isolation** | 2 | 0 | 2 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **Cross-Cutting: Golden Accounting Vectors** | 3 | 0 | 3 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **Cross-Cutting: Tenant Company Isolation** | 3 | 0 | 3 | 0 Passed (100% Failed) | 0 Passed (100% Failed) |
| **Total Specification Suites** | **32** | **39** | **71** | **0 / 71 (0.00%)** | **0 / 71 (0.00%)** |

---

## 4. Test Tightness Analysis

### 4.1 Why `ThrowingStubKernel` Fails 100%
- Happy-path tests immediately abort with `RuntimeException` instead of producing successful business records, IDs, or states.
- Violation tests assert specific typed domain exceptions defined under `Dakhl\Cleanroom\Core\Exceptions\*` (e.g., `UnbalancedJournalVoucherException`, `FiscalPeriodLockedException`, `DuplicateAccountCodeException`). Because PHPUnit's `expectException()` is class-specific, generic `RuntimeException` instances thrown by the stub cause immediate test failure with:
  ```
  Failed asserting that exception of type "RuntimeException" matches expected exception "Dakhl\Cleanroom\Core\Exceptions\...".
  ```

### 4.2 Why `NeutralStubKernel` Fails 100%
- Returning `false` or `0` fails numerical, mathematical, and identity assertions:
  - Account balance aggregation tests assert the exact sum of child accounts ($> 0$), failing on `0`.
  - Sequential numbering tests assert monotonic progression ($1, 2, 3...$), failing on `0`.
  - Half-up rounding tests assert exact Rial integers (e.g. `166667`), failing on `0`.
  - Inventory moving average asserts exact cost per unit (`10000.0`), failing on `0.0`.
  - Landed cost distribution tests assert exact allocation down to the Rial on highest-value line (`120000`), failing on `null`.
- Violation tests in the negative suite expect an exception to be thrown. Since `NeutralStubKernel` quietly returns neutral values instead of throwing, all 39 violation tests fail with:
  ```
  Failed asserting that exception of type "Dakhl\Cleanroom\Core\Exceptions\..." is thrown.
  ```

---

## 5. Conclusion & Certification

The test suite in `C:\Sandbox\dakhl-cleanroom-core\` has been proven resilient against false positives and stub evasion. It establishes an unyielding red baseline that can only transition to green through genuine, mathematically exact, and invariant-compliant domain logic implemented by the clean-room engineer.
