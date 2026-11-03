# Dakhl Clean-Room Core Integration & Migration Plan
**Document Version:** 1.0.0  
**Target Architecture:** Clean-Room Core Engine (`Dakhl\Core\...`)  
**Security Classification:** RESTRICTED INTERNAL ARCHITECTURE  
**Target Release Phase:** Clean-Room Milestone Core Cutover  
**Status:** APPROVED FOR EXECUTION  

---

## 1. Executive Summary & Purpose

This document defines the formal, end-to-end integration plan for incorporating the clean-room core accounting engine developed in `C:\Sandbox\dakhl-cleanroom-core\` into the primary Dakhl codebase (`c:\Projects\HesaBora\`).

The clean-room engine replaces legacy, heritage-tainted transactional and posting mechanisms with a pristine, PSR-12 compliant, mathematically verifiable, and tamper-resistant domain core conforming to ADR-A through ADR-E (Decisions D-131 through D-135).

---

## 2. Architectural Boundaries & Namespace Mapping

### 2.1 File & Directory Placement
The clean-room code produced by the Implementer under `src/` will be ported into the main application under `app/Core/`:

| Clean-Room Sandbox Path | Host Application Target Path | Namespace |
| :--- | :--- | :--- |
| `src/Kernel/CoreKernel.php` | `app/Core/Kernel/CoreKernel.php` | `Dakhl\Core\Kernel` |
| `src/Accounts/ChartOfAccountsService.php` | `app/Core/Accounts/ChartOfAccountsService.php` | `Dakhl\Core\Accounts` |
| `src/Numbering/DocumentNumberingService.php` | `app/Core/Numbering/DocumentNumberingService.php` | `Dakhl\Core\Numbering` |
| `src/Posting/JournalVoucherPostingService.php` | `app/Core/Posting/JournalVoucherPostingService.php` | `Dakhl\Core\Posting` |
| `src/Audit/AuditReversalService.php` | `app/Core/Audit/AuditReversalService.php` | `Dakhl\Core\Audit` |
| `src/Period/PeriodFreezeService.php` | `app/Core/Period/PeriodFreezeService.php` | `Dakhl\Core\Period` |
| `src/Commercial/InvoicePostingService.php` | `app/Core/Commercial/InvoicePostingService.php` | `Dakhl\Core\Commercial` |
| `src/Commercial/InvoiceMutationGuard.php` | `app/Core/Commercial/InvoiceMutationGuard.php` | `Dakhl\Core\Commercial` |
| `src/Inventory/CogsValuationService.php` | `app/Core/Inventory/CogsValuationService.php` | `Dakhl\Core\Inventory` |
| `src/Costs/AncillaryCostService.php` | `app/Core/Costs/AncillaryCostService.php` | `Dakhl\Core\Costs` |
| `src/Treasury/TreasuryPaymentService.php` | `app/Core/Treasury/TreasuryPaymentService.php` | `Dakhl\Core\Treasury` |
| `src/Settlement/PaymentAllocationService.php` | `app/Core/Settlement/PaymentAllocationService.php` | `Dakhl\Core\Settlement` |
| `src/Cheque/ChequeInstrumentService.php` | `app/Core/Cheque/ChequeInstrumentService.php` | `Dakhl\Core\Cheque` |
| `src/Assets/FixedAssetDepreciationService.php` | `app/Core/Assets/FixedAssetDepreciationService.php` | `Dakhl\Core\Assets` |
| `src/CostChain/DependencyRecalculationService.php` | `app/Core/CostChain/DependencyRecalculationService.php` | `Dakhl\Core\CostChain` |
| `src/Provenance/ProvenanceTrackingService.php` | `app/Core/Provenance/ProvenanceTrackingService.php` | `Dakhl\Core\Provenance` |
| `src/Closing/FiscalClosingService.php` | `app/Core/Closing/FiscalClosingService.php` | `Dakhl\Core\Closing` |
| `contracts/` | `app/Core/Contracts/` | `Dakhl\Cleanroom\Core\Contracts` |
| `contracts/Exceptions/` | `app/Core/Exceptions/` | `Dakhl\Cleanroom\Core\Exceptions` |
| `contracts/DTO/` | `app/Core/DTO/` | `Dakhl\Cleanroom\Core\DTO` |

---

## 3. Service Container Bootstrapping & DI Bindings

All domain contracts will be registered in `app/Providers/AppServiceProvider.php` (or a dedicated `app/Providers/CleanroomCoreServiceProvider.php`):

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Dakhl\Cleanroom\Core\Contracts\CoreKernelContract;
use Dakhl\Core\Kernel\CoreKernel;

class CleanroomCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CoreKernelContract::class, function ($app) {
            $pdo = DB::connection()->getPdo();
            return new CoreKernel($pdo);
        });

        // Register domain contracts via the authoritative CoreKernel
        $contracts = [
            \Dakhl\Cleanroom\Core\Contracts\SubjectHierarchyContract::class => 'accounts',
            \Dakhl\Cleanroom\Core\Contracts\DocumentNumberingContract::class => 'numbering',
            \Dakhl\Cleanroom\Core\Contracts\JournalVoucherPostingContract::class => 'posting',
            \Dakhl\Cleanroom\Core\Contracts\AuditReversalContract::class => 'reversals',
            \Dakhl\Cleanroom\Core\Contracts\PeriodFreezeGuardContract::class => 'periods',
            \Dakhl\Cleanroom\Core\Contracts\CommercialInvoicePostingContract::class => 'invoicePosting',
            \Dakhl\Cleanroom\Core\Contracts\InventoryValuationCogsContract::class => 'inventory',
            \Dakhl\Cleanroom\Core\Contracts\AncillaryCostContract::class => 'ancillaryCosts',
            \Dakhl\Cleanroom\Core\Contracts\TreasuryPaymentContract::class => 'treasury',
            \Dakhl\Cleanroom\Core\Contracts\PaymentAllocationContract::class => 'allocations',
            \Dakhl\Cleanroom\Core\Contracts\ChequeInstrumentContract::class => 'cheques',
            \Dakhl\Cleanroom\Core\Contracts\FixedAssetDepreciationContract::class => 'fixedAssets',
            \Dakhl\Cleanroom\Core\Contracts\CostDependencyRecalculationContract::class => 'costRecalculation',
            \Dakhl\Cleanroom\Core\Contracts\CommercialInvoiceLifecycleContract::class => 'invoiceLifecycle',
            \Dakhl\Cleanroom\Core\Contracts\ProvenanceTrackingContract::class => 'provenance',
            \Dakhl\Cleanroom\Core\Contracts\FiscalClosingContract::class => 'closing',
        ];

        foreach ($contracts as $interface => $method) {
            $this->app->bind($interface, function ($app) use ($method) {
                return $app->make(CoreKernelContract::class)->$method();
            });
        }
    }
}
```

---

## 4. Bridge Adapters & Legacy Service Migration

To ensure zero downtime and maintain backwards compatibility across existing controllers, jobs, and tests, Bridge Adapters will be introduced:

```
[ HTTP Controller / Livewire / API ]
                 │
                 ▼
      [ Service Bridge Adapter ]
                 │
                 ▼
[ Dakhl Clean-Room Core Kernel ] (app/Core/...)
                 │
                 ▼
     [ PDO / Database Engine ] (SQLite / MariaDB)
```

1. **`InvoiceService` Adapter:** Delegating status validation, draft finalization, and journal voucher posting to `CommercialInvoicePostingContract` and `CommercialInvoiceLifecycleContract`.
2. **`PaymentService` Adapter:** Routing payment creation and allocations through `TreasuryPaymentContract` and `PaymentAllocationContract`.
3. **`CostOfGoodsService` Adapter:** Routing FIFO / Weighted Average calculations and perpetual movement records through `InventoryValuationCogsContract`.
4. **`ChequeService` Adapter:** Routing state transitions through `ChequeInstrumentContract` state machine.
5. **`FiscalYearService` Adapter:** Routing year closing, temporary account zeroing, and opening balance voucher creation through `FiscalClosingContract`.

---

## 5. Database Schema & Migration Execution Strategy

1. **Schema Additions (Non-Breaking):**
   - Execute Laravel migrations adding new tables: `numbering_leases`, `fixed_assets`, `stock_movements`, `posting_settings`, `audit_events`.
   - Add `provenance` and `binary(16) uuid` columns where not already present.
2. **Constraint Enforcement (ADR-A & ADR-D):**
   - Apply line XOR CHECK constraints (`chk_jvl_amounts`).
   - Validate and ensure `ON DELETE RESTRICT` foreign keys.
3. **Trigger Installation (ADR-E):**
   - Install `BEFORE UPDATE` and `BEFORE DELETE` immutability triggers on `journal_vouchers` and `journal_voucher_lines`.

---

## 6. Phased Rollout & Verification Checkpoints

### Phase 1: Shadow Execution (Dark Launch)
- The clean-room engine runs in parallel with legacy services in non-blocking observation mode.
- Output journal vouchers and balances are compared. Any discrepancy triggers an immediate alert.

### Phase 2: Domain-by-Domain Cutover
- **Step 1:** Chart of Accounts & Document Numbering (Specs 01-02).
- **Step 2:** Treasury & Payments (Specs 09-10).
- **Step 3:** Cheques & Financial Instruments (Spec 11).
- **Step 4:** Commercial Invoices & COGS (Specs 06-08).
- **Step 5:** Period Freezing & Year-End Closing (Specs 04-05, 16).

### Phase 3: Legacy Retirement
- Deprecate legacy transaction builders (`*TransactionBuilder.php`).
- Remove obsolete un-scoped methods.
- Final code cleanup and test baseline lock.

---

## 7. Rollback & Disaster Recovery Procedures

1. **Feature Flags:** Each clean-room domain adapter is wrapped with a config toggle (`config('accounting.driver') = 'cleanroom' | 'legacy'`).
2. **Instant Fallback:** If any unhandled exception or data mismatch is detected during Phase 2, the system automatically falls back to legacy path for that request and logs the incident.
3. **Data Integrity:** Because the clean-room engine writes to the canonical relational tables with stricter invariants, no rollback data migration is required.
