<?php

namespace App\Services\Migration;

use App\Enums\ChequeType;
use App\Enums\FinancialProvenance;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\Migration\Adapters\HolooBackupAdapter;
use App\Services\Migration\Adapters\UniversalJsonAdapter;
use App\Services\Migration\Contracts\MigrationAdapterInterface;
use App\Services\Migration\DTO\HealthCheckResult;
use App\Services\Migration\DTO\MigrationCommitResult;
use App\Services\Migration\DTO\MigrationPayload;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CompetitorMigrationService
{
    /**
     * @var MigrationAdapterInterface[]
     */
    protected array $adapters;

    public function __construct()
    {
        $this->adapters = [
            new UniversalJsonAdapter,
            new HolooBackupAdapter,
        ];
    }

    public function resolveAdapter(string $sourceType, array|string $data): MigrationAdapterInterface
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($sourceType, $data)) {
                return $adapter;
            }
        }

        return new UniversalJsonAdapter;
    }

    public function parse(array|string $data, string $sourceType = 'universal_json'): MigrationPayload
    {
        $adapter = $this->resolveAdapter($sourceType, $data);
        return $adapter->parse($data);
    }

    public function analyzeAndHealthCheck(MigrationPayload $payload): HealthCheckResult
    {
        $result = new HealthCheckResult;

        $totalCustomerDebtors = 0.0;
        $totalCustomerCreditors = 0.0;
        $totalInventoryValuation = 0.0;
        $totalReceivableCheques = 0.0;
        $totalPayableCheques = 0.0;

        // 1. Audit Customers
        foreach ($payload->customers as $index => $c) {
            $name = trim($c['name'] ?? '');
            if (empty($name)) {
                $result->addError("ردیف مشتری #{$index} فاقد نام معتبر می‌باشد.");
            }

            $bal = (float) ($c['balance'] ?? 0);
            if ($bal >= 0) {
                $totalCustomerDebtors += $bal;
            } else {
                $totalCustomerCreditors += abs($bal);
            }
        }

        // 2. Audit Products & Stock
        foreach ($payload->products as $index => $p) {
            $name = trim($p['name'] ?? '');
            if (empty($name)) {
                $result->addError("ردیف کالا #{$index} فاقد عنوان معتبر است.");
            }

            $stock = (float) ($p['initial_stock'] ?? 0);
            $buyPrice = (float) ($p['purchase_price'] ?? 0);
            $sellPrice = (float) ($p['sell_price'] ?? 0);

            if ($stock < 0) {
                $result->addWarning("کالای '{$name}' دارای موجودی منفی ({$stock}) در سیستم قدیمی بوده است. در فرآیند خودکار، موجودی صفر لحاظ خواهد شد.");
            } else {
                $totalInventoryValuation += ($stock * $buyPrice);
            }

            if ($sellPrice > 0 && $buyPrice > $sellPrice) {
                $result->addWarning("قیمت خرید کالای '{$name}' بیشتر از قیمت فروش ثبت شده در سیستم قدیمی است.");
            }
        }

        // 3. Audit Cheques
        foreach ($payload->cheques as $index => $chq) {
            $amount = (float) ($chq['amount'] ?? 0);
            if ($amount <= 0) {
                $result->addError("چک ردیف #{$index} دارای مبلغ نامعتبر (صفر یا منفی) است.");
            }

            $sayad = trim($chq['sayad_number'] ?? '');
            if (! empty($sayad) && (strlen($sayad) !== 16 || ! ctype_digit($sayad))) {
                $result->addWarning("شماره صیاد چک '{$chq['cheque_number']}' ساختار استاندارد ۱۶ رقمی ندارد.");
            }

            $dir = strtolower($chq['direction'] ?? 'receivable');
            if ($dir === 'receivable') {
                $totalReceivableCheques += $amount;
            } else {
                $totalPayableCheques += $amount;
            }
        }

        // 4. Double-Entry Balance Sheet Equation
        $totalAssets = $totalCustomerDebtors + $totalInventoryValuation + $totalReceivableCheques;
        $totalLiabilities = $totalCustomerCreditors + $totalPayableCheques;
        $netEquityImbalance = $totalAssets - $totalLiabilities;

        $result->reconciliation = [
            'total_debtors' => $totalCustomerDebtors,
            'total_creditors' => $totalCustomerCreditors,
            'total_inventory_valuation' => $totalInventoryValuation,
            'total_receivable_cheques' => $totalReceivableCheques,
            'total_payable_cheques' => $totalPayableCheques,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'opening_equity' => $netEquityImbalance,
            'is_balanced' => true, // Will balance via Equity/Opening Balance Account
        ];

        return $result;
    }

    public function autoRepair(MigrationPayload $payload): array
    {
        $repaired = [];

        // Fix negative stocks
        foreach ($payload->products as $i => &$p) {
            if (($p['initial_stock'] ?? 0) < 0) {
                $repaired[] = "موجودی منفی کالای '{$p['name']}' از {$p['initial_stock']} به 0 تغییر یافت.";
                $p['initial_stock'] = 0;
            }
        }

        // Sanitize phone numbers
        foreach ($payload->customers as $i => &$c) {
            if (! empty($c['mobile'])) {
                $c['mobile'] = preg_replace('/[^0-9]/', '', $c['mobile']);
            }
        }

        return $repaired;
    }

    public function commitMigration(
        User $user,
        int $companyId,
        MigrationPayload $payload,
        bool $autoRepair = true
    ): MigrationCommitResult {
        $repaired = [];
        if ($autoRepair) {
            $repaired = $this->autoRepair($payload);
        }

        $health = $this->analyzeAndHealthCheck($payload);
        if ($health->hasErrors()) {
            return MigrationCommitResult::failure(
                'خطاهای مسدودکننده در داده‌های ورودی مانع از مهاجرت گردید: ' .
                collect($health->errors)->pluck('message')->implode(' | ')
            );
        }

        return DB::transaction(function () use ($user, $companyId, $payload, $health, $repaired) {
            // 1. Resolve or Create default Customer Group & Customers
            $customerGroup = CustomerGroup::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->first();

            if (! $customerGroup) {
                $customerGroup = CustomerGroup::create([
                    'company_id' => $companyId,
                    'name' => 'مشتریان انتقالی',
                    'description' => 'مشتریان وارد شده از نرم‌افزار قدیمی',
                ]);
            }

            $customerDebtorsSubject = $this->resolveOrCreateSubject(
                $companyId,
                'حساب بدهکاران تجاری (مشتریان)',
                '10301'
            );

            $customerCreditorsSubject = $this->resolveOrCreateSubject(
                $companyId,
                'حساب بستانکاران تجاری (تامین‌کنندگان)',
                '20101'
            );

            $createdCustomers = [];
            foreach ($payload->customers as $cData) {
                $cust = Customer::create([
                    'company_id' => $companyId,
                    'name' => $cData['name'],
                    'mobile' => $cData['mobile'] ?? null,
                    'group_id' => $customerGroup->id,
                    'subject_id' => $customerDebtorsSubject->id,
                ]);
                $createdCustomers[$cust->name] = $cust;
            }

            // 2. Resolve or Create default Product Group & Products
            $productGroup = ProductGroup::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->first();

            if (! $productGroup) {
                $productGroup = ProductGroup::create([
                    'company_id' => $companyId,
                    'name' => 'کالاهای انتقالی',
                ]);
            }

            $inventorySubject = $this->resolveOrCreateSubject(
                $companyId,
                'موجودی کالا (انبار مرکزی)',
                '10401'
            );

            $importedProductsCount = 0;
            foreach ($payload->products as $pData) {
                Product::create([
                    'company_id' => $companyId,
                    'name' => $pData['name'],
                    'code' => ! empty($pData['code']) ? $pData['code'] : ('PRD-' . mt_rand(1000, 9999)),
                    'barcode' => ! empty($pData['barcode']) ? $pData['barcode'] : null,
                    'group' => $productGroup->id,
                    'selling_price' => $pData['sell_price'] ?? 0,
                    'average_cost' => $pData['purchase_price'] ?? 0,
                    'quantity' => $pData['initial_stock'] ?? 0,
                    'inventory_subject_id' => $inventorySubject->id,
                ]);
                $importedProductsCount++;
            }

            // 3. Register Cheques
            $chqRecSubject = $this->resolveOrCreateSubject(
                $companyId,
                'اسناد دریافتنی (چک‌های در جریان)',
                '10501'
            );

            $chqPaySubject = $this->resolveOrCreateSubject(
                $companyId,
                'اسناد پرداختنی (چک‌های صادره)',
                '20201'
            );

            $importedChequesCount = 0;
            foreach ($payload->cheques as $chqData) {
                $isReceivable = strtolower($chqData['direction'] ?? 'receivable') === 'receivable';
                $customerName = $chqData['customer_name'] ?? '';
                $customerObj = ! empty($customerName) && isset($createdCustomers[$customerName])
                    ? $createdCustomers[$customerName]
                    : Customer::withoutGlobalScopes()->where('company_id', $companyId)->first();

                Cheque::create([
                    'company_id' => $companyId,
                    'customer_id' => $customerObj?->id,
                    'direction' => $isReceivable ? ChequeType::RECEIVABLE : ChequeType::PAYABLE,
                    'purpose' => ChequeType::SETTLEMENT,
                    'status' => ChequeType::REGISTERED,
                    'amount' => (float) $chqData['amount'],
                    'write_date' => $chqData['write_date'] ?? Carbon::today()->toDateString(),
                    'due_date' => $chqData['due_date'] ?? Carbon::today()->addDays(30)->toDateString(),
                    'cheque_number' => ! empty($chqData['cheque_number']) ? $chqData['cheque_number'] : ('CHK-' . mt_rand(10000, 99999)),
                    'sayad_number' => ! empty($chqData['sayad_number']) ? $chqData['sayad_number'] : null,
                    'desc' => 'چک انتقالی از سیستم مالی قدیمی',
                ]);
                $importedChequesCount++;
            }

            // 4. Construct Opening Journal Entry (سند افتتاحیه)
            $equitySubject = $this->resolveOrCreateSubject(
                $companyId,
                'سرمایه و تراز افتتاحیه (مهاجرت سیستم)',
                '30101'
            );

            $transactions = [];
            $recon = $health->reconciliation;

            // Debits (Assets)
            if ($recon['total_debtors'] > 0) {
                $transactions[] = [
                    'subject_id' => $customerDebtorsSubject->id,
                    'desc' => 'مانده بدهی مشتریان طبق تراز افتتاحیه سیستم قدیم',
                    'value' => (float) $recon['total_debtors'],
                ];
            }

            if ($recon['total_inventory_valuation'] > 0) {
                $transactions[] = [
                    'subject_id' => $inventorySubject->id,
                    'desc' => 'ارزش ریالی موجودی اولیه انبار کالای سیستم قدیم',
                    'value' => (float) $recon['total_inventory_valuation'],
                ];
            }

            if ($recon['total_receivable_cheques'] > 0) {
                $transactions[] = [
                    'subject_id' => $chqRecSubject->id,
                    'desc' => 'مجموع اسناد و چک‌های دریافتنی درجریان وصول سیستم قدیم',
                    'value' => (float) $recon['total_receivable_cheques'],
                ];
            }

            // Credits (Liabilities)
            if ($recon['total_creditors'] > 0) {
                $transactions[] = [
                    'subject_id' => $customerCreditorsSubject->id,
                    'desc' => 'مانده بستانکاری تامین‌کنندگان طبق تراز افتتاحیه سیستم قدیم',
                    'value' => -(float) $recon['total_creditors'],
                ];
            }

            if ($recon['total_payable_cheques'] > 0) {
                $transactions[] = [
                    'subject_id' => $chqPaySubject->id,
                    'desc' => 'مجموع اسناد و چک‌های پرداختنی سررسید‌نشده سیستم قدیم',
                    'value' => -(float) $recon['total_payable_cheques'],
                ];
            }

            // Equity balancing entry
            $currentSum = collect($transactions)->sum('value');
            if (abs($currentSum) > 0.001) {
                $transactions[] = [
                    'subject_id' => $equitySubject->id,
                    'desc' => 'تراز افتتاحیه و حقوق صاحبان سهام / اختلاف خالص دارایی‌ها و بدهی‌های انتقالی',
                    'value' => -$currentSum,
                ];
            }

            // Create Opening Document
            $openingDoc = DocumentService::createDocument($user, [
                'company_id' => $companyId,
                'date' => Carbon::today()->toDateString(),
                'title' => 'سند افتتاحیه مالی انتقال از سیستم قدیمی',
                'number' => null,
            ], $transactions);

            // D-085: Stamp provenance as BASELINE (never NATIVE) for historical/imported documents
            $openingDoc->provenance = FinancialProvenance::BASELINE;
            $openingDoc->save();

            DocumentService::changeDocumentStatus($openingDoc, $user, 'approved');

            return MigrationCommitResult::success(
                customersCount: count($createdCustomers),
                productsCount: $importedProductsCount,
                chequesCount: $importedChequesCount,
                openingDocument: $openingDoc,
                repaired: $repaired
            );
        });
    }

    protected function resolveOrCreateSubject(int $companyId, string $name, string $code): Subject
    {
        $subject = Subject::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($name, $code) {
                $q->where('code', $code)->orWhere('name', $name);
            })
            ->first();

        if ($subject) {
            return $subject;
        }

        return Subject::create([
            'company_id' => $companyId,
            'name' => $name,
            'code' => $code,
            'parent_id' => null,
        ]);
    }
}
