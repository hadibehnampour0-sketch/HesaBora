<?php

declare(strict_types=1);

namespace App\Providers;

use Dakhl\Cleanroom\Core\Contracts\AncillaryCostContract;
use Dakhl\Cleanroom\Core\Contracts\AuditReversalContract;
use Dakhl\Cleanroom\Core\Contracts\ChequeInstrumentContract;
use Dakhl\Cleanroom\Core\Contracts\CommercialInvoiceLifecycleContract;
use Dakhl\Cleanroom\Core\Contracts\CommercialInvoicePostingContract;
use Dakhl\Cleanroom\Core\Contracts\CoreKernelContract;
use Dakhl\Cleanroom\Core\Contracts\CostDependencyRecalculationContract;
use Dakhl\Cleanroom\Core\Contracts\DocumentNumberingContract;
use Dakhl\Cleanroom\Core\Contracts\FiscalClosingContract;
use Dakhl\Cleanroom\Core\Contracts\FixedAssetDepreciationContract;
use Dakhl\Cleanroom\Core\Contracts\InventoryValuationCogsContract;
use Dakhl\Cleanroom\Core\Contracts\JournalVoucherPostingContract;
use Dakhl\Cleanroom\Core\Contracts\PaymentAllocationContract;
use Dakhl\Cleanroom\Core\Contracts\PeriodFreezeGuardContract;
use Dakhl\Cleanroom\Core\Contracts\ProvenanceTrackingContract;
use Dakhl\Cleanroom\Core\Contracts\SubjectHierarchyContract;
use Dakhl\Cleanroom\Core\Contracts\TreasuryPaymentContract;
use Dakhl\Core\Bridge\CleanroomChequeBridge;
use Dakhl\Core\Bridge\CleanroomFiscalClosingBridge;
use Dakhl\Core\Bridge\CleanroomInventoryBridge;
use Dakhl\Core\Bridge\CleanroomInvoiceBridge;
use Dakhl\Core\Bridge\CleanroomTreasuryBridge;
use Dakhl\Core\Kernel\CoreKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class CleanroomCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CoreKernelContract::class, function () {
            $pdo = DB::connection()->getPdo();

            return new CoreKernel($pdo);
        });

        $contracts = [
            SubjectHierarchyContract::class => 'accounts',
            DocumentNumberingContract::class => 'numbering',
            JournalVoucherPostingContract::class => 'posting',
            AuditReversalContract::class => 'reversals',
            PeriodFreezeGuardContract::class => 'periods',
            CommercialInvoicePostingContract::class => 'invoicePosting',
            InventoryValuationCogsContract::class => 'inventory',
            AncillaryCostContract::class => 'ancillaryCosts',
            TreasuryPaymentContract::class => 'treasury',
            PaymentAllocationContract::class => 'allocations',
            ChequeInstrumentContract::class => 'cheques',
            FixedAssetDepreciationContract::class => 'fixedAssets',
            CostDependencyRecalculationContract::class => 'costRecalculation',
            CommercialInvoiceLifecycleContract::class => 'invoiceLifecycle',
            ProvenanceTrackingContract::class => 'provenance',
            FiscalClosingContract::class => 'closing',
        ];

        foreach ($contracts as $interface => $method) {
            $this->app->bind($interface, function ($app) use ($method) {
                return $app->make(CoreKernelContract::class)->$method();
            });
        }

        // Register Clean-Room Bridge Adapters
        $this->app->singleton(CleanroomInvoiceBridge::class);
        $this->app->singleton(CleanroomTreasuryBridge::class);
        $this->app->singleton(CleanroomInventoryBridge::class);
        $this->app->singleton(CleanroomChequeBridge::class);
        $this->app->singleton(CleanroomFiscalClosingBridge::class);
    }
}
