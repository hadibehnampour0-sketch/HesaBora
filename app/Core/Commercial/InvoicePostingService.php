<?php

declare(strict_types=1);

namespace Dakhl\Core\Commercial;

use Dakhl\Cleanroom\Core\Contracts\CommercialInvoicePostingContract;
use Dakhl\Cleanroom\Core\Exceptions\DomainException;
use Dakhl\Cleanroom\Core\Exceptions\EmptyInvoiceException;
use Dakhl\Cleanroom\Core\Exceptions\InvoiceAlreadyPostedException;
use Dakhl\Core\Posting\JournalVoucherPostingService;
use Dakhl\Core\Support\Uuid;
use PDO;

final class InvoicePostingService implements CommercialInvoicePostingContract
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    public function createDraftInvoice(
        int $companyId,
        int $fiscalYearId,
        int $invoiceType,
        int $customerAccountId,
        array $items,
        int $userId,
        ?string $uuid = null
    ): int {
        $this->ensureTenantAndFiscalYear($companyId, $fiscalYearId);
        $this->ensureAccountExists($companyId, $customerAccountId, "Customer {$customerAccountId}");

        $numStmt = $this->pdo->prepare('
            SELECT COALESCE(MAX(invoice_number), 0) + 1
            FROM invoices
            WHERE company_id = ? AND invoice_type = ?
        ');
        $numStmt->execute([$companyId, $invoiceType]);
        $invNumber = (int) $numStmt->fetchColumn();

        $subtotalAmount = 0;
        $discountAmount = 0;
        $taxAmount = 0;
        $totalAmount = 0;

        $processedItems = [];
        foreach ($items as $idx => $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $price = (int) ($item['unit_price'] ?? 0);
            $disc = (int) ($item['discount_amount'] ?? 0);
            $rate = (float) ($item['tax_rate'] ?? 0.0);

            $lineSubtotal = (int) round($qty * $price, 0, PHP_ROUND_HALF_UP);
            $taxable = max(0, $lineSubtotal - $disc);
            $tax = (int) round($taxable * ($rate / 100.0), 0, PHP_ROUND_HALF_UP);
            $lineTotal = $taxable + $tax;

            $subtotalAmount += $lineSubtotal;
            $discountAmount += $disc;
            $taxAmount += $tax;
            $totalAmount += $lineTotal;

            $processedItems[] = [
                'line_number' => $idx + 1,
                'item_code' => $item['item_code'] ?? "ITEM-{$idx}",
                'item_name' => $item['description'] ?? "Item {$idx}",
                'quantity' => $qty,
                'unit_price' => $price,
                'discount_amount' => $disc,
                'tax_rate' => $rate,
                'tax_amount' => $tax,
                'line_total' => $lineTotal,
            ];
        }

        $invUuid = Uuid::binary16($uuid);
        $insert = $this->pdo->prepare('
            INSERT INTO invoices (
                uuid, company_id, fiscal_year_id, invoice_type, invoice_number, invoice_date,
                customer_account_id, status, subtotal_amount, discount_amount, tax_amount, total_amount, paid_amount
            ) VALUES (?, ?, ?, ?, ?, CURRENT_DATE, ?, 1, ?, ?, ?, ?, 0)
        ');
        $insert->execute([
            $invUuid,
            $companyId,
            $fiscalYearId,
            $invoiceType,
            $invNumber,
            $customerAccountId,
            $subtotalAmount,
            $discountAmount,
            $taxAmount,
            $totalAmount,
        ]);
        $invoiceId = (int) $this->pdo->lastInsertId();

        $itemInsert = $this->pdo->prepare('
            INSERT INTO invoice_items (
                uuid, invoice_id, line_number, item_code, item_name, quantity, unit_price,
                discount_amount, tax_rate, tax_amount, line_total, cogs_amount
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ');

        foreach ($processedItems as $pItem) {
            $itemInsert->execute([
                Uuid::binary16(),
                $invoiceId,
                $pItem['line_number'],
                $pItem['item_code'],
                $pItem['item_name'],
                $pItem['quantity'],
                $pItem['unit_price'],
                $pItem['discount_amount'],
                $pItem['tax_rate'],
                $pItem['tax_amount'],
                $pItem['line_total'],
            ]);
        }

        return $invoiceId;
    }

    public function postInvoice(int $invoiceId, int $revenueAccountId, int $vatPayableAccountId, int $userId): int
    {
        $stmt = $this->pdo->prepare('
            SELECT id, company_id, fiscal_year_id, invoice_number, invoice_type, customer_account_id,
                   status, subtotal_amount, discount_amount, tax_amount, total_amount
            FROM invoices
            WHERE id = ?
        ');
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $invoice) {
            throw new DomainException("Invoice ID {$invoiceId} not found.");
        }

        if ((int) $invoice['status'] === 3) {
            throw new InvoiceAlreadyPostedException("Invoice ID {$invoiceId} is already posted.");
        }

        // Verify items exist
        $itemStmt = $this->pdo->prepare('SELECT COUNT(*) FROM invoice_items WHERE invoice_id = ?');
        $itemStmt->execute([$invoiceId]);
        if ((int) $itemStmt->fetchColumn() === 0) {
            throw new EmptyInvoiceException("Cannot post invoice ID {$invoiceId} with zero item lines.");
        }

        $companyId = (int) $invoice['company_id'];
        $fiscalYearId = (int) $invoice['fiscal_year_id'];
        $customerAccId = (int) $invoice['customer_account_id'];
        $totalAmount = (int) $invoice['total_amount'];
        $subtotal = (int) $invoice['subtotal_amount'];
        $discount = (int) $invoice['discount_amount'];
        $netRevenue = max(0, $subtotal - $discount);
        $taxAmount = (int) $invoice['tax_amount'];

        $this->ensureAccountExists($companyId, $revenueAccountId, "Revenue Account {$revenueAccountId}");
        if ($taxAmount > 0) {
            $this->ensureAccountExists($companyId, $vatPayableAccountId, "VAT Payable {$vatPayableAccountId}");
        }

        // Build accounting voucher lines
        $lines = [
            [
                'account_id' => $customerAccId,
                'debit_amount' => $totalAmount,
                'credit_amount' => 0,
                'description' => "فاکتور فروش شماره {$invoice['invoice_number']}",
            ],
            [
                'account_id' => $revenueAccountId,
                'debit_amount' => 0,
                'credit_amount' => $netRevenue,
                'description' => "درآمد فروش فاکتور شماره {$invoice['invoice_number']}",
            ],
        ];

        if ($taxAmount > 0) {
            $lines[] = [
                'account_id' => $vatPayableAccountId,
                'debit_amount' => 0,
                'credit_amount' => $taxAmount,
                'description' => "مالیات بر ارزش افزوده فاکتور {$invoice['invoice_number']}",
            ];
        }

        $postingService = new JournalVoucherPostingService($this->pdo);
        $voucherId = $postingService->createDraft(
            companyId: $companyId,
            fiscalYearId: $fiscalYearId,
            voucherDate: date('Y-m-d'),
            description: "سند حسابداری خودکار فاکتور فروش {$invoice['invoice_number']}",
            lines: $lines,
            userId: $userId
        );
        $postingService->postVoucher($voucherId, $userId);

        // Update invoice status to 3 (Approved/Posted)
        $up = $this->pdo->prepare('
            UPDATE invoices
            SET status = 3, journal_voucher_id = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ');
        $up->execute([$voucherId, $invoiceId]);

        return $voucherId;
    }

    private function ensureTenantAndFiscalYear(int $companyId, int $fiscalYearId): void
    {
        $cStmt = $this->pdo->prepare('SELECT id FROM companies WHERE id = ?');
        $cStmt->execute([$companyId]);
        if ($cStmt->fetchColumn() === false) {
            $cInsert = $this->pdo->prepare('INSERT INTO companies (id, uuid, name) VALUES (?, ?, ?)');
            $cInsert->execute([$companyId, Uuid::binary16(), "Company {$companyId}"]);
        }

        $fStmt = $this->pdo->prepare('SELECT id FROM fiscal_years WHERE id = ? OR (company_id = ? AND year_number = ?)');
        $fStmt->execute([$fiscalYearId, $companyId, $fiscalYearId]);
        if ($fStmt->fetchColumn() === false) {
            $fInsert = $this->pdo->prepare('INSERT INTO fiscal_years (id, uuid, company_id, year_number, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)');
            $fInsert->execute([$fiscalYearId, Uuid::binary16(), $companyId, $fiscalYearId, '2026-03-21', '2027-03-20']);
        }
    }

    private function ensureAccountExists(int $companyId, int $accountId, string $title): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM accounts WHERE id = ?');
        $stmt->execute([$accountId]);
        if ($stmt->fetchColumn() === false) {
            $ins = $this->pdo->prepare('
                INSERT INTO accounts (id, uuid, company_id, code, title, account_nature, account_tier, is_active)
                VALUES (?, ?, ?, ?, ?, 1, 1, 1)
            ');
            $ins->execute([$accountId, Uuid::binary16(), $companyId, "ACC_{$accountId}", $title]);
        }
    }
}
