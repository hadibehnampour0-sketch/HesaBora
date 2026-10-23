<?php

namespace App\Services\Migration\Adapters;

use App\Services\Migration\Contracts\MigrationAdapterInterface;
use App\Services\Migration\DTO\MigrationPayload;

class SepidarBackupAdapter implements MigrationAdapterInterface
{
    public function getName(): string
    {
        return 'sepidar';
    }

    public function supports(string $sourceType, array|string $data): bool
    {
        if ($sourceType === 'sepidar') {
            return true;
        }

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        if (is_array($data)) {
            // Sepidar export signature keys
            if (isset($data['Parties']) || isset($data['Party']) || isset($data['Items']) || isset($data['Item']) || isset($data['Cheques'])) {
                return true;
            }

            // Check if any list element has Sepidar specific field names like PartyCode, ItemCode
            if (! empty($data['customers'][0]['PartyCode']) || ! empty($data['products'][0]['ItemCode'])) {
                return true;
            }
        }

        return false;
    }

    public function parse(array|string $data): MigrationPayload
    {
        if (is_string($data)) {
            $data = json_decode($data, true) ?: [];
        }

        $rawParties = $data['Parties'] ?? $data['Party'] ?? $data['parties'] ?? $data['customers'] ?? [];
        $rawItems = $data['Items'] ?? $data['Item'] ?? $data['items'] ?? $data['products'] ?? [];
        $rawCheques = $data['Cheques'] ?? $data['cheques'] ?? $data['ReceivedCheques'] ?? [];

        // 1. Map Parties / Customers
        $customers = [];
        foreach ($rawParties as $p) {
            $name = trim($p['PartyName'] ?? $p['Name'] ?? $p['Title'] ?? '');
            if (empty($name)) {
                continue;
            }

            $debit = (float) ($p['DebitBalance'] ?? $p['Debit'] ?? $p['Bed'] ?? 0);
            $credit = (float) ($p['CreditBalance'] ?? $p['Credit'] ?? $p['Bes'] ?? 0);
            $balance = (float) ($p['Remain'] ?? $p['Balance'] ?? ($debit - $credit));

            $customers[] = [
                'name' => $name,
                'code' => trim($p['PartyCode'] ?? $p['Code'] ?? ''),
                'mobile' => trim($p['Mobile'] ?? $p['Phone'] ?? $p['Tell'] ?? ''),
                'national_code' => trim($p['NationalCode'] ?? $p['NationalID'] ?? $p['EconomicCode'] ?? ''),
                'balance' => $balance,
                'is_creditor' => $balance < 0,
                'group_name' => $p['PartyGroupTitle'] ?? $p['Group'] ?? 'مشتریان سپیدار',
                'address' => $p['Address'] ?? null,
            ];
        }

        // 2. Map Items / Products
        $products = [];
        foreach ($rawItems as $item) {
            $name = trim($item['ItemName'] ?? $item['Name'] ?? $item['Title'] ?? '');
            if (empty($name)) {
                continue;
            }

            $purchasePrice = (float) ($item['PurchasePrice'] ?? $item['BuyPrice'] ?? $item['CostPrice'] ?? 0);
            $salePrice = (float) ($item['SalePrice'] ?? $item['SellPrice'] ?? $item['Price'] ?? 0);
            $stock = (float) ($item['StockQuantity'] ?? $item['AvailableStock'] ?? $item['Stock'] ?? $item['Quantity'] ?? 0);

            $products[] = [
                'name' => $name,
                'code' => trim($item['ItemCode'] ?? $item['Code'] ?? ''),
                'barcode' => trim($item['Barcode'] ?? $item['BarCode'] ?? ''),
                'unit' => $item['UnitName'] ?? $item['Unit'] ?? 'عدد',
                'purchase_price' => $purchasePrice,
                'sell_price' => $salePrice,
                'initial_stock' => $stock,
                'group_name' => $item['ItemGroupTitle'] ?? $item['Group'] ?? 'کالاهای سپیدار',
            ];
        }

        // 3. Map Cheques
        $cheques = [];
        foreach ($rawCheques as $chq) {
            $amount = (float) ($chq['Amount'] ?? $chq['Mablagh'] ?? 0);
            $type = (int) ($chq['Type'] ?? $chq['ChequeType'] ?? 1); // 1 = Receivable, 2 = Payable
            $direction = ($type === 2 || strtolower($chq['Direction'] ?? '') === 'payable') ? 'payable' : 'receivable';

            $cheques[] = [
                'cheque_number' => trim($chq['ChequeNumber'] ?? $chq['Number'] ?? $chq['Serial'] ?? ''),
                'sayad_number' => trim($chq['SayadNumber'] ?? $chq['Sayad'] ?? ''),
                'customer_name' => trim($chq['PartyName'] ?? $chq['DrawerTitle'] ?? $chq['Payer'] ?? $chq['CustomerName'] ?? ''),
                'amount' => $amount,
                'write_date' => $chq['ChequeDate'] ?? $chq['IssueDate'] ?? now()->toDateString(),
                'due_date' => $chq['DueDate'] ?? $chq['Sarresid'] ?? now()->addDays(30)->toDateString(),
                'direction' => $direction,
                'bank_name' => $chq['BankName'] ?? 'بانک سپیدار',
            ];
        }

        return new MigrationPayload(
            customers: $customers,
            products: $products,
            cheques: $cheques,
            chartOfAccounts: [],
            journalEntries: [],
            metadata: [
                'source' => 'sepidar_backup',
                'adapter' => 'SepidarBackupAdapter',
            ]
        );
    }
}
