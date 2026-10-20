<?php

namespace App\Services\Migration\Adapters;

use App\Services\Migration\Contracts\MigrationAdapterInterface;
use App\Services\Migration\DTO\MigrationPayload;

class HolooBackupAdapter implements MigrationAdapterInterface
{
    public function getName(): string
    {
        return 'holoo';
    }

    public function supports(string $sourceType, array|string $data): bool
    {
        if ($sourceType === 'holoo') {
            return true;
        }

        if (is_array($data) && (isset($data['TarafHesab']) || isset($data['Kala']) || isset($data['Cheque']))) {
            return true;
        }

        return false;
    }

    public function parse(array|string $data): MigrationPayload
    {
        if (is_string($data)) {
            $data = json_decode($data, true) ?: [];
        }

        $customers = [];
        foreach ($data['TarafHesab'] ?? [] as $t) {
            $bed = (float) ($t['C_Bed'] ?? 0);
            $bes = (float) ($t['C_Bes'] ?? 0);
            $balance = $bed - $bes;

            $customers[] = [
                'name' => trim($t['C_Name'] ?? $t['Name'] ?? ''),
                'mobile' => trim($t['C_Mobile'] ?? $t['C_Tell'] ?? $t['Tel'] ?? ''),
                'national_code' => trim($t['C_Melli'] ?? $t['Melli'] ?? ''),
                'balance' => $balance,
                'is_creditor' => $bes > $bed,
                'group_name' => $t['C_Group'] ?? 'مشتریان هلو',
            ];
        }

        $products = [];
        foreach ($data['Kala'] ?? [] as $k) {
            $products[] = [
                'name' => trim($k['K_Name'] ?? $k['Name'] ?? ''),
                'code' => trim($k['K_Code'] ?? $k['Code'] ?? ''),
                'barcode' => trim($k['K_Barcode'] ?? $k['Barcode'] ?? ''),
                'unit' => $k['K_Vahed'] ?? 'عدد',
                'purchase_price' => (float) ($k['K_Price_Buy'] ?? $k['PriceBuy'] ?? 0),
                'sell_price' => (float) ($k['K_Price_Sell'] ?? $k['PriceSell'] ?? 0),
                'initial_stock' => (float) ($k['K_Mojoodi'] ?? $k['Mojoodi'] ?? 0),
                'group_name' => $k['K_Group'] ?? 'کالاهای هلو',
            ];
        }

        $cheques = [];
        foreach ($data['Cheque'] ?? [] as $chq) {
            $direction = (isset($chq['Chq_Noe']) && (int) $chq['Chq_Noe'] === 2) ? 'payable' : 'receivable';

            $cheques[] = [
                'cheque_number' => trim($chq['Chq_Number'] ?? $chq['Serial'] ?? ''),
                'sayad_number' => trim($chq['Chq_Sayad'] ?? $chq['Sayad'] ?? ''),
                'customer_name' => trim($chq['Chq_TarafHesab'] ?? $chq['Payer'] ?? ''),
                'amount' => (float) ($chq['Chq_Mablagh'] ?? $chq['Mablagh'] ?? 0),
                'write_date' => $chq['Chq_Tarikh_Sodor'] ?? now()->toDateString(),
                'due_date' => $chq['Chq_Tarikh_Sarresid'] ?? now()->addDays(30)->toDateString(),
                'direction' => $direction,
                'bank_name' => $chq['Chq_Bank'] ?? 'بانک ملی',
            ];
        }

        return new MigrationPayload(
            customers: $customers,
            products: $products,
            cheques: $cheques,
            chartOfAccounts: [],
            journalEntries: [],
            metadata: ['source' => 'holoo_backup']
        );
    }
}
