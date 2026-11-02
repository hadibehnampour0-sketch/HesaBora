<?php

namespace App\Services\Trade;

use App\Models\Customer;
use App\Models\Subject;
use App\Models\TradeExchange;
use App\Models\TradeExchangeItem;
use App\Models\User;
use App\Services\DocumentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TradeExchangeService
{
    /**
     * Create a new trade/barter exchange with a neighbor store or partner.
     */
    public function createExchange(
        int $companyId,
        int $customerId,
        string $title = 'تبادل و تهاتر کالا با همکار',
        ?int $createdBy = null,
        ?string $notes = null,
        array $items = []
    ): TradeExchange {
        return DB::transaction(function () use ($companyId, $customerId, $title, $createdBy, $notes, $items) {
            $exchange = TradeExchange::create([
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'title' => $title,
                'status' => 'open',
                'created_by' => $createdBy,
                'notes' => $notes,
            ]);

            foreach ($items as $itemData) {
                $this->addItem(
                    exchange: $exchange,
                    direction: $itemData['direction'] ?? 'out',
                    itemName: $itemData['item_name'],
                    quantity: (float) ($itemData['quantity'] ?? 1),
                    unitPrice: (float) ($itemData['unit_price'] ?? 0),
                    productId: $itemData['product_id'] ?? null,
                    unit: $itemData['unit'] ?? 'عدد',
                    notes: $itemData['notes'] ?? null
                );
            }

            return $exchange;
        });
    }

    /**
     * Add a leg/item (given or taken) to an existing exchange.
     */
    public function addItem(
        TradeExchange $exchange,
        string $direction,
        string $itemName,
        float $quantity,
        float $unitPrice = 0,
        ?int $productId = null,
        string $unit = 'عدد',
        ?string $notes = null
    ): TradeExchangeItem {
        if (! in_array($direction, ['out', 'in'])) {
            throw new InvalidArgumentException("جهت جابجایی نامعتبر است؛ فقط 'out' (داده شده) یا 'in' (گرفته شده) مجاز است.");
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException('تعداد جابجایی کالا باید بزرگتر از صفر باشد.');
        }

        return TradeExchangeItem::create([
            'trade_exchange_id' => $exchange->id,
            'direction' => $direction,
            'product_id' => $productId,
            'item_name' => trim($itemName),
            'quantity' => $quantity,
            'unit' => $unit ?: 'عدد',
            'unit_price' => max(0, $unitPrice),
            'returned_quantity' => 0,
            'notes' => $notes,
        ]);
    }

    /**
     * Record a return of an item (e.g. counterparty returned 1 cover or we returned 3 glasses).
     */
    public function recordReturn(TradeExchangeItem $item, float $returnedQty): TradeExchangeItem
    {
        if ($returnedQty <= 0) {
            throw new InvalidArgumentException('تعداد مرجوعی باید مثبت باشد.');
        }

        $newReturned = $item->returned_quantity + $returnedQty;
        if ($newReturned > $item->quantity) {
            throw new InvalidArgumentException('تعداد مرجوعی نمی‌تواند بیشتر از تعداد کل جابجا شده باشد.');
        }

        $item->update([
            'returned_quantity' => $newReturned,
        ]);

        return $item;
    }

    /**
     * Settle an open exchange (via Cash, Goods in-kind, or Ledger adjustment).
     */
    public function settleExchange(
        TradeExchange $exchange,
        User $user,
        string $settlementType = 'goods',
        ?float $agreedCashAmount = null,
        ?string $notes = null
    ): TradeExchange {
        return DB::transaction(function () use ($exchange, $user, $settlementType, $agreedCashAmount, $notes) {
            $summary = $exchange->calculateValuationSummary();
            $netValuation = $agreedCashAmount !== null ? $agreedCashAmount : (float) $summary['net_valuation'];

            $settlementNotes = $notes ?: match ($settlementType) {
                'cash' => 'تسویه ریالی/نقدی تفاضل ارزش اقلام تهاتر شده به مبلغ '.number_format(abs($netValuation)).' ریال',
                'goods' => 'تسویه و توافق جنسی متقابل اقلام تبادل شده با همکار',
                'ledger' => 'تسویه و انتقال مانده خالص تهاتر به دفتر حساب شخص',
                default => 'تسویه تبادل اقلام با همکار',
            };

            $documentId = null;

            // Generate double-entry accounting document when there is financial movement (ledger or cash)
            if (abs($netValuation) > 0.005 && in_array($settlementType, ['ledger', 'cash'])) {
                $customer = $exchange->customer;
                $customerSubjectId = $customer?->subject_id ?? $customer?->subject?->id;
                if (! $customerSubjectId) {
                    $customerSubjectId = (int) (Subject::withoutGlobalScopes()->where('company_id', $exchange->company_id)->where('code', config('amir.cust_subject'))->value('id')
                        ?? Subject::withoutGlobalScopes()->where('company_id', $exchange->company_id)->value('id')
                        ?? throw new \DomainException(__('حساب طرف حساب برای این شرکت تعریف نشده است.')));
                }

                if ($settlementType === 'cash') {
                    $counterpartSubjectId = (int) (config('amir.cash') ?? config('amir.cash_book') ?? $customerSubjectId);
                } else {
                    $counterpartSubjectId = $netValuation > 0
                        ? (int) (config('amir.income') ?? config('amir.sales_revenue') ?? $customerSubjectId)
                        : (int) (config('amir.sundry_cost') ?? config('amir.cost') ?? $customerSubjectId);
                }

                $amount = abs($netValuation);
                $transactions = [
                    [
                        'subject_id' => $customerSubjectId,
                        'desc' => $settlementNotes,
                        'value' => $netValuation > 0 ? -$amount : $amount,
                    ],
                    [
                        'subject_id' => $counterpartSubjectId,
                        'desc' => $settlementNotes,
                        'value' => $netValuation > 0 ? $amount : -$amount,
                    ],
                ];

                $document = DocumentService::createDocument($user, [
                    'date' => now()->toDateString(),
                    'title' => 'سند تسویه تهاتر تبادل کالا #'.$exchange->id.' ('.$exchange->title.')',
                    'number' => null,
                    'company_id' => $exchange->company_id,
                    'documentable' => $exchange,
                ], $transactions);

                DocumentService::changeDocumentStatus($document, $user, 'approved');
                $documentId = $document->id;
            }

            $exchange->update([
                'status' => 'settled',
                'settlement_type' => $settlementType,
                'settlement_notes' => $settlementNotes,
                'settled_at' => Carbon::now(),
                'document_id' => $documentId,
            ]);

            return $exchange;
        });
    }

    /**
     * Find or create active open exchange for a partner/customer.
     */
    public function findOrCreateActiveExchange(int $companyId, int $customerId, ?int $createdBy = null): TradeExchange
    {
        $existing = TradeExchange::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['open', 'partially_settled'])
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        $customer = Customer::withoutGlobalScopes()->find($customerId);
        $title = 'تبادل جاری با '.($customer?->name ?? 'همکار');

        return $this->createExchange(
            companyId: $companyId,
            customerId: $customerId,
            title: $title,
            createdBy: $createdBy
        );
    }
}
