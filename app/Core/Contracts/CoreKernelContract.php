<?php

declare(strict_types=1);

namespace Dakhl\Cleanroom\Core\Contracts;

/**
 * Single Authoritative Entry Point for the Dakhl Clean-Room Accounting Engine.
 *
 * The Implementer implements this contract, providing access to all 16 domain engines.
 */
interface CoreKernelContract
{
    public function accounts(): SubjectHierarchyContract;

    public function numbering(): DocumentNumberingContract;

    public function posting(): JournalVoucherPostingContract;

    public function reversals(): AuditReversalContract;

    public function periods(): PeriodFreezeGuardContract;

    public function invoicePosting(): CommercialInvoicePostingContract;

    public function inventory(): InventoryValuationCogsContract;

    public function ancillaryCosts(): AncillaryCostContract;

    public function treasury(): TreasuryPaymentContract;

    public function allocations(): PaymentAllocationContract;

    public function cheques(): ChequeInstrumentContract;

    public function fixedAssets(): FixedAssetDepreciationContract;

    public function costRecalculation(): CostDependencyRecalculationContract;

    public function invoiceLifecycle(): CommercialInvoiceLifecycleContract;

    public function provenance(): ProvenanceTrackingContract;

    public function closing(): FiscalClosingContract;
}
