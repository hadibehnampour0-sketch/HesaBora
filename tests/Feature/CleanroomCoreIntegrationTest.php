<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invoice;
use Dakhl\Cleanroom\Core\Contracts\CoreKernelContract;
use Dakhl\Cleanroom\Core\Contracts\SubjectHierarchyContract;
use Dakhl\Core\Accounts\ChartOfAccountsService;
use Dakhl\Core\Bridge\CleanroomChequeBridge;
use Dakhl\Core\Bridge\CleanroomFiscalClosingBridge;
use Dakhl\Core\Bridge\CleanroomInventoryBridge;
use Dakhl\Core\Bridge\CleanroomInvoiceBridge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CleanroomCoreIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanroom_core_kernel_and_contracts_resolve_from_laravel_container(): void
    {
        $kernel = app(CoreKernelContract::class);
        $this->assertNotNull($kernel);

        $accounts = app(SubjectHierarchyContract::class);
        $this->assertNotNull($accounts);
        $this->assertInstanceOf(ChartOfAccountsService::class, $accounts);
    }

    public function test_cleanroom_invoice_bridge_mutation_guard(): void
    {
        $bridge = app(CleanroomInvoiceBridge::class);

        // Dummy invoice instance
        $invoice = new Invoice;
        $invoice->id = 999999;

        // An invoice without any financial activity can be mutated
        $this->assertTrue($bridge->canMutate($invoice));

        // Calling assertCanMutate should not throw
        $bridge->assertCanMutate($invoice);
        $this->assertTrue(true);
    }

    public function test_cleanroom_inventory_bridge_moving_weighted_average(): void
    {
        $inventory = app(CleanroomInventoryBridge::class);

        $companyId = 99;
        $itemCode = 'PROD-BRIDGE-01';

        // Inbound: 10 units @ 100,000 Rial = 1,000,000 Rial
        $inventory->recordInbound($companyId, $itemCode, '10.000000', 1000000);
        $this->assertEquals(100000.0, $inventory->getCurrentAverageUnitCost($companyId, $itemCode));

        // Outbound COGS for 4 units: 4 * 100,000 = 400,000 Rial
        $cogs = $inventory->calculateCogs($companyId, $itemCode, '4.000000');
        $this->assertSame(400000, $cogs);
    }

    public function test_cleanroom_cheque_bridge_state_transitions(): void
    {
        $cheques = app(CleanroomChequeBridge::class);

        $companyId = 88;
        $fiscalYearId = 1405;

        $chequeId = $cheques->registerReceivedCheque(
            companyId: $companyId,
            fiscalYearId: $fiscalYearId,
            chequeNumber: 'CHK-BRG-1',
            sayadNumber: '9988776655443322',
            bankName: 'بانک پاسارگاد',
            dueDate: '2026-06-20',
            amount: 12000000,
            customerAccountId: 201,
            notesReceivableAccountId: 105,
            userId: 1
        );

        $this->assertGreaterThan(0, $chequeId);

        // Deposit cheque
        $cheques->depositCheque($chequeId, collectionAccountId: 106, userId: 1);

        // Clear cheque
        $voucherId = $cheques->clearCheque($chequeId, bankAccountId: 101, userId: 1);
        $this->assertGreaterThanOrEqual(0, $voucherId);
    }

    public function test_cleanroom_fiscal_closing_bridge_workflow(): void
    {
        $closing = app(CleanroomFiscalClosingBridge::class);

        $companyId = 77;
        $sourceYear = 1404;
        $targetYear = 1405;

        // Close temporary accounts
        $closingVoucherId = $closing->closeTemporaryAccounts(
            companyId: $companyId,
            fiscalYearId: $sourceYear,
            retainedEarningsAccountId: 305,
            userId: 1
        );
        $this->assertGreaterThan(0, $closingVoucherId);

        // Create opening voucher
        $openingVoucherId = $closing->createOpeningVoucher(
            companyId: $companyId,
            sourceFiscalYearId: $sourceYear,
            targetFiscalYearId: $targetYear,
            userId: 1
        );
        $this->assertGreaterThan(0, $openingVoucherId);
    }
}
