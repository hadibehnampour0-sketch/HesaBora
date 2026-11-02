# Dakhl Clean-Room Implementer Execution Protocol
**Target Implementation Directory:** `C:\Sandbox\dakhl-cleanroom-core\`  
**Target Code Namespace:** `Dakhl\Core\...` (`C:\Sandbox\dakhl-cleanroom-core\src\`)  
**Specification Baseline:** SPEC_01 through SPEC_16 (Decisions D-131 through D-135)  
**Security Classification:** ISOLATED CLEAN-ROOM ENVIRONMENT  

---

## 1. Environment & Sandbox Setup

### 1.1 Physical & Virtual Isolation
1. **Isolated Execution Context:** The Implementer session must execute in a strictly isolated environment (a dedicated OS user account, isolated Docker container, or sandboxed VM).
2. **Filesystem Boundaries:** The session must only have read and write permissions to `C:\Sandbox\dakhl-cleanroom-core\`. Access to the host application repository (`c:\Projects\HesaBora\`), upstream mirrors, or legacy backup archives is strictly blocked.
3. **No VCS History:** The sandbox must not contain `.git` or any version control history. The Implementer begins with a clean slate.
4. **Network Restrictions:** The Implementer requires no outbound internet access. Database access is restricted to `localhost:3306` (MariaDB `dakhl_test`) and local SQLite memory/files.

---

## 2. Implementer Input Package
The Implementer receives exclusively the following five assets in `C:\Sandbox\dakhl-cleanroom-core\`:
1. `contracts/`: 8 clean PHP 8.4 interfaces under namespace `Dakhl\Cleanroom\Core\Contracts\*`.
2. `specs/`: 16 behavioral specifications (`SPEC_01` through `SPEC_16`) detailing inputs, mathematical formulas, business rules, invariants, and error conditions.
3. `schema/`: SQLite and MariaDB DDL definitions conforming to ADR-A through ADR-E.
4. `tests/`: 16 PHPUnit characterization test suites (62 tests total) mapping 1:1 with specifications.
5. `verify_sandbox_hygiene.py`: Automated hygiene script.

---

## 3. Implementer Development Workflow

### Step 1: Initial Baseline Verification
Before writing any code, the Implementer runs PHPUnit to confirm the starting state:
```bash
php vendor/phpunit/phpunit/phpunit -c phpunit.xml
```
**Expected Initial Result:** 62 tests run, 62 failed (100% RED baseline), 0 passed.

### Step 2: Implementation Target & Structure
All implementation code must be written under `src/` with namespace `Dakhl\Core\...`:
```
C:\Sandbox\dakhl-cleanroom-core\src\
├── Accounts\
│   └── ChartOfAccountsService.php
├── Numbering\
│   └── DocumentNumberingService.php
├── Posting\
│   └── JournalVoucherPostingService.php
├── Audit\
│   └── AuditReversalService.php
├── Period\
│   └── PeriodFreezeService.php
├── Commercial\
│   ├── InvoicePostingService.php
│   └── InvoiceMutationGuard.php
├── Inventory\
│   └── CogsValuationService.php
├── Costs\
│   └── AncillaryCostService.php
├── Treasury\
│   └── TreasuryPaymentService.php
├── Settlement\
│   └── PaymentAllocationService.php
├── Cheque\
│   └── ChequeInstrumentService.php
├── Assets\
│   └── FixedAssetDepreciationService.php
├── CostChain\
│   └── DependencyRecalculationService.php
├── Provenance\
│   └── ProvenanceTrackingService.php
└── Closing\
    └── FiscalClosingService.php
```

### Step 3: Implement to Pass the Test Suites
The Implementer works through the 16 specifications systematically:
- Implements each service class to satisfy the public contracts.
- Uses standard PHP 8.4+ features, typed properties, match expressions, and explicit exceptions.
- Implements `ROUND_HALF_UP` whole-Rial money math through explicit helpers (ADR-B).
- Respects database transaction boundaries and pessimistic row locking (`FOR UPDATE`).

### Step 4: Verification Against Both Database Engines
1. Verify on SQLite:
   ```bash
   php vendor/phpunit/phpunit/phpunit -c phpunit.xml
   ```
2. Verify on MariaDB `dakhl_test`:
   ```powershell
   $env:DB_CONNECTION="mysql"; $env:DB_DATABASE="dakhl_test"; php vendor/phpunit/phpunit/phpunit -c phpunit.xml
   ```
**Target Final Result:** 62 tests run, 62 passed (100% GREEN) on BOTH engines.

---

## 4. Porting Back & Verifier Certification Protocol

Once the Implementer finishes, the **Verifier** (a separate session) executes the porting procedure:

### Phase 1: Sandbox Hygiene & Token Audit
Run the automated hygiene script:
```bash
python C:\Sandbox\dakhl-cleanroom-core\verify_sandbox_hygiene.py
```
Must return: `Sandbox Status: 100% CLEAN & HYGIENIC`.

### Phase 2: Similarity Scans
Run structural and token-level similarity scans comparing `src/` against legacy code:
- AST Structural Similarity: Must be **< 20%** on every single file.
- N-gram Token Similarity: Must be **< 20%** on every single file.
- Directory Composite Score: Must be **< 15%**.

### Phase 3: Integration into Main Repository
1. The Verifier copies `src/` into the main application under `app/Core/Cleanroom/`.
2. Connects service container bindings in `app/Providers/AppServiceProvider.php`.
3. Runs the full application test suite:
   - Full suite on SQLite (2,184+ tests): Must be 100% GREEN.
   - Full suite on MariaDB `dakhl_test` (2,184+ tests): Must be 100% GREEN.
4. Commits as a clean-room baseline milestone in git.
