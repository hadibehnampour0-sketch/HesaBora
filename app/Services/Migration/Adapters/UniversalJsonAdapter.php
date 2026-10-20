<?php

namespace App\Services\Migration\Adapters;

use App\Services\Migration\Contracts\MigrationAdapterInterface;
use App\Services\Migration\DTO\MigrationPayload;

class UniversalJsonAdapter implements MigrationAdapterInterface
{
    public function getName(): string
    {
        return 'universal_json';
    }

    public function supports(string $sourceType, array|string $data): bool
    {
        if ($sourceType === 'universal_json' || $sourceType === 'json') {
            return true;
        }

        if (is_array($data) && (isset($data['customers']) || isset($data['products']) || isset($data['cheques']))) {
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
        foreach ($data['customers'] ?? [] as $c) {
            $balance = (float) ($c['balance'] ?? $c['initial_balance'] ?? 0);
            $customers[] = [
                'name' => trim($c['name'] ?? ''),
                'mobile' => trim($c['mobile'] ?? $c['phone'] ?? ''),
                'national_code' => trim($c['national_code'] ?? ''),
                'balance' => $balance,
                'is_creditor' => (bool) ($c['is_creditor'] ?? ($balance < 0)),
                'group_name' => $c['group_name'] ?? 'مشتریان مهاجرت داده شده',
            ];
        }

        $products = [];
        foreach ($data['products'] ?? [] as $p) {
            $products[] = [
                'name' => trim($p['name'] ?? ''),
                'code' => trim($p['code'] ?? ''),
                'barcode' => trim($p['barcode'] ?? ''),
                'unit' => $p['unit'] ?? 'عدد',
                'purchase_price' => (float) ($p['purchase_price'] ?? $p['buy_price'] ?? 0),
                'sell_price' => (float) ($p['sell_price'] ?? 0),
                'initial_stock' => (float) ($p['initial_stock'] ?? $p['stock'] ?? 0),
                'group_name' => $p['group_name'] ?? 'کالاهای مهاجرت داده شده',
            ];
        }

        $cheques = [];
        foreach ($data['cheques'] ?? [] as $chq) {
            $cheques[] = [
                'cheque_number' => trim($chq['cheque_number'] ?? $chq['number'] ?? ''),
                'sayad_number' => trim($chq['sayad_number'] ?? ''),
                'customer_name' => trim($chq['customer_name'] ?? $chq['payer'] ?? ''),
                'amount' => (float) ($chq['amount'] ?? 0),
                'write_date' => $chq['write_date'] ?? now()->toDateString(),
                'due_date' => $chq['due_date'] ?? now()->addDays(30)->toDateString(),
                'direction' => $chq['direction'] ?? 'receivable', // receivable or payable
                'bank_name' => $chq['bank_name'] ?? 'بانک ملت',
            ];
        }

        return new MigrationPayload(
            customers: $customers,
            products: $products,
            cheques: $cheques,
            chartOfAccounts: $data['chart_of_accounts'] ?? [],
            journalEntries: $data['journal_entries'] ?? [],
            metadata: $data['metadata'] ?? ['source' => 'universal_json']
        );
    }
}
