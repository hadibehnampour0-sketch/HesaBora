<?php

declare(strict_types=1);

namespace Dakhl\Core\Kernel;

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
use Dakhl\Core\Accounts\ChartOfAccountsService;
use Dakhl\Core\Assets\FixedAssetDepreciationService;
use Dakhl\Core\Audit\AuditReversalService;
use Dakhl\Core\Cheque\ChequeInstrumentService;
use Dakhl\Core\Closing\FiscalClosingService;
use Dakhl\Core\Commercial\InvoiceMutationGuard;
use Dakhl\Core\Commercial\InvoicePostingService;
use Dakhl\Core\CostChain\DependencyRecalculationService;
use Dakhl\Core\Costs\AncillaryCostService;
use Dakhl\Core\Inventory\CogsValuationService;
use Dakhl\Core\Numbering\DocumentNumberingService;
use Dakhl\Core\Period\PeriodFreezeService;
use Dakhl\Core\Posting\JournalVoucherPostingService;
use Dakhl\Core\Provenance\ProvenanceTrackingService;
use Dakhl\Core\Settlement\PaymentAllocationService;
use Dakhl\Core\Treasury\TreasuryPaymentService;
use PDO;

final class CoreKernel implements CoreKernelContract
{
    public function __construct(private PDO $pdo)
    {
    }

    public function accounts(): SubjectHierarchyContract
    {
        return new ChartOfAccountsService($this->pdo);
    }

    public function numbering(): DocumentNumberingContract
    {
        return new DocumentNumberingService($this->pdo);
    }

    public function posting(): JournalVoucherPostingContract
    {
        return new JournalVoucherPostingService($this->pdo);
    }

    public function reversals(): AuditReversalContract
    {
        return new AuditReversalService($this->pdo);
    }

    public function periods(): PeriodFreezeGuardContract
    {
        return new PeriodFreezeService($this->pdo);
    }

    public function invoicePosting(): CommercialInvoicePostingContract
    {
        return new InvoicePostingService($this->pdo);
    }

    public function inventory(): InventoryValuationCogsContract
    {
        return new CogsValuationService($this->pdo);
    }

    public function ancillaryCosts(): AncillaryCostContract
    {
        return new AncillaryCostService($this->pdo);
    }

    public function treasury(): TreasuryPaymentContract
    {
        return new TreasuryPaymentService($this->pdo);
    }

    public function allocations(): PaymentAllocationContract
    {
        return new PaymentAllocationService($this->pdo);
    }

    public function cheques(): ChequeInstrumentContract
    {
        return new ChequeInstrumentService($this->pdo);
    }

    public function fixedAssets(): FixedAssetDepreciationContract
    {
        return new FixedAssetDepreciationService($this->pdo);
    }

    public function costRecalculation(): CostDependencyRecalculationContract
    {
        return new DependencyRecalculationService($this->pdo);
    }

    public function invoiceLifecycle(): CommercialInvoiceLifecycleContract
    {
        return new InvoiceMutationGuard($this->pdo);
    }

    public function provenance(): ProvenanceTrackingContract
    {
        return new ProvenanceTrackingService($this->pdo);
    }

    public function closing(): FiscalClosingContract
    {
        return new FiscalClosingService($this->pdo);
    }
}
