<?php

namespace App\Services\AiCopilot;

use App\Models\Customer;
use App\Models\Product;
use App\Services\PriceQuoteService;
use App\Services\Trade\TradeExchangeService;

/**
 * Deterministic Financial Chat Engine.
 * Provides instant, zero-cost, privacy-safe, fully offline responses in Persian
 * by parsing financial intents, accounting action commands, trade exchanges,
 * and price quotations with verified memory recall.
 */
class DeterministicFinancialChatEngine
{
    public function __construct(
        protected FinancialKnowledgeService $knowledgeService,
        protected PriceQuoteService $priceQuoteService = new PriceQuoteService,
        protected TradeExchangeService $tradeExchangeService = new TradeExchangeService
    ) {}

    public function answer(int $companyId, string $query): array
    {
        $normalized = mb_strtolower(trim($query));
        $normalizedDigits = $this->normalizeDigits($normalized);
        $toolsUsed = [];
        $sources = [];

        // -------------------------------------------------------------
        // A. Price Quote Memory Recall Intent ("به علی قیمت گلس چند دادم؟")
        // -------------------------------------------------------------
        if ($this->isPriceQuoteQueryIntent($normalized)) {
            $toolsUsed[] = 'price_quote_memory_recall';
            $sources[] = ['title' => 'دفترچه استعلام قیمت‌ها', 'url' => route('price-quotes.index')];

            return $this->handlePriceQuoteRecall($companyId, $normalizedDigits);
        }

        // -------------------------------------------------------------
        // B. Price Quote Store Intent ("به علی قیمت گلس رو ۴۰ تومن دادم ثبت کن")
        // -------------------------------------------------------------
        if ($this->isPriceQuoteStoreIntent($normalizedDigits)) {
            $toolsUsed[] = 'price_quote_recorder';
            $sources[] = ['title' => 'دفترچه استعلام قیمت‌ها', 'url' => route('price-quotes.index')];

            return $this->handlePriceQuoteStore($companyId, $normalizedDigits);
        }

        // -------------------------------------------------------------
        // C. Peer Barter / Trade Intent ("۲ تا کاور دادم رضا", "۵ تا گلس از رضا گرفتم")
        // -------------------------------------------------------------
        if ($this->isPeerTradeIntent($normalizedDigits)) {
            $toolsUsed[] = 'trade_exchange_action';
            $sources[] = ['title' => 'میز کار تهاتر با همکار', 'url' => route('trades.index')];

            return $this->handlePeerTrade($companyId, $normalizedDigits);
        }

        // -------------------------------------------------------------
        // D. Conversational Payment / Receipt Intent ("۲۰۰ تومن دادم علی", "۳۰۰ از حسن گرفتم")
        // -------------------------------------------------------------
        if ($this->isConversationalPaymentIntent($normalizedDigits)) {
            $toolsUsed[] = 'quick_payment_action_card';
            $sources[] = ['title' => 'ثبت پرداخت و دریافت سریع', 'url' => route('quick-payment.create', ['direction' => 'receive'])];

            return $this->handleConversationalPayment($companyId, $normalizedDigits);
        }

        // -------------------------------------------------------------
        // 1. Sales & Revenue Intent
        // -------------------------------------------------------------
        if ($this->matchesKeywords($normalized, ['فروش', 'درآمد', 'فاکتور فروش', 'فروختیم', 'دخل امروز', 'فروش امروز', 'فروش هفته'])) {
            $toolsUsed[] = 'sales_summary';
            $sources[] = ['title' => 'داشبورد فاکتورها', 'url' => route('invoices.index')];
            $sales = $this->knowledgeService->getSalesSummary($companyId);

            $salesToday = number_format($sales['sales_today']);
            $salesWeek = number_format($sales['sales_7_days']);
            $salesMonth = number_format($sales['sales_this_month']);
            $purchasesMonth = number_format($sales['purchases_this_month']);
            $net = number_format($sales['net_operational_flow']);

            $reply = "### 📊 گزارش عملکرد فروش و درآمد شما\n\n";
            $reply .= "بر اساس آخرین فاکتورهای رسمی و قطعی ثبت‌شده در سیستم دخل:\n\n";
            $reply .= "- **فروش امروز:** `{$salesToday}` ریال ({$sales['sales_today_count']} فاکتور صادرشده)\n";
            $reply .= "- **فروش ۷ روز گذشته:** `{$salesWeek}` ریال\n";
            $reply .= "- **مجموع فروش این ماه:** `{$salesMonth}` ریال\n";
            $reply .= "- **خریدهای این ماه:** `{$purchasesMonth}` ریال\n";
            $reply .= "- **تراز عملیاتی ماه جاری:** `{$net}` ریال\n\n";

            if ($sales['net_operational_flow'] > 0) {
                $reply .= '💡 **تحلیل دستیار:** تراز عملیاتی ماهانه مثبت است و ورودی فاکتورهای فروش نسبت به خریدها برتری دارد. پیشنهاد می‌شود جهت وصول به موقع مطالبات ناشی از این فروش، لینک‌های پرداخت آنلاین را برای مشتریان فعال کنید.';
            } else {
                $reply .= '⚠️ **تحلیل دستیار:** حجم خریدهای این ماه نسبت به فروش بالاتر است؛ این امر در صورت انباشت موجودی انبار طبیعی است اما جریان نقدینگی کوتاه‌مدت را نیازمند مراقبت می‌کند.';
            }

            return [
                'reply' => $reply,
                'tool_calls' => $toolsUsed,
                'sources' => $sources,
            ];
        }

        // -------------------------------------------------------------
        // 2. Debtors & Overdue Receivables Intent
        // -------------------------------------------------------------
        if ($this->matchesKeywords($normalized, ['بدهکار', 'طلب', 'مطالبات', 'معوق', 'چه کسانی بدهکارن', 'بدهی مشتری', 'وصول'])) {
            $toolsUsed[] = 'ageing_debtors';
            $sources[] = ['title' => 'گزارش سنی مطالبات', 'url' => route('ageing.receivables')];
            $debtors = $this->knowledgeService->getTopOverdueDebtors(5);

            $total = number_format($debtors['total_receivables']);
            $reply = "### 👥 وضعیت مطالبات و مشتریان بدهکار\n\n";
            $reply .= "مجموع مطالبات باز شما از مشتریان: **`{$total}` ریال** است.\n\n";

            if (empty($debtors['top_debtors'])) {
                $reply .= '🟢 خوشبختانه در حال حاضر هیچ مطالبه معوق با ریسک بالا ثبت نشده است و حساب مشتریان به‌روز می‌باشد.';
            } else {
                $reply .= "| نام مشتری | مانده کل بدهی (ریال) | بدهی بالای ۹۰ روز | فاکتورهای باز |\n";
                $reply .= "| :--- | :---: | :---: | :---: |\n";
                foreach ($debtors['top_debtors'] as $d) {
                    $dTotal = number_format($d['total_debt']);
                    $dOver = number_format($d['over_90_days']);
                    $reply .= "| **{$d['name']}** | {$dTotal} | {$dOver} | {$d['invoices_count']} فاکتور |\n";
                }

                $reply .= "\n📌 **اقدام پیشنهادی:** برای مشتریانی که بدهی بالای ۹۰ روز دارند، می‌توانید از بخش [ارسال پیامک یادآوری هوشمند](".route('sms.index').') یا صدور [لینک پرداخت شاپرک]('.route('payment-links.index').') جهت وصول سریع‌تر استفاده نمایید.';
            }

            return [
                'reply' => $reply,
                'tool_calls' => $toolsUsed,
                'sources' => $sources,
            ];
        }

        // -------------------------------------------------------------
        // 3. Cheques & Treasury Intent
        // -------------------------------------------------------------
        if ($this->matchesKeywords($normalized, ['چک', 'صیاد', 'سررسید', 'برگشتی', 'پاس نشده', 'چک دریافتی', 'چک پرداختی', 'خزانه'])) {
            $toolsUsed[] = 'cheques_report';
            $sources[] = ['title' => 'مدیریت چک‌ها', 'url' => route('cheques.index')];
            $cheques = $this->knowledgeService->getChequesDueSoon(7);

            $recDue = number_format($cheques['receivable_due_soon_amount']);
            $recOver = number_format($cheques['receivable_overdue_amount']);
            $recBounced = number_format($cheques['receivable_bounced_amount']);
            $payDue = number_format($cheques['payable_due_soon_amount']);

            $reply = "### 💳 خلاصه وضعیت چک‌های دریافتی و پرداختی (افق ۷ روزه)\n\n";
            $reply .= "- **چک‌های دریافتی سررسید این هفته:** {$cheques['receivable_due_soon_count']} فقره به مبلغ `{$recDue}` ریال\n";
            $reply .= "- **چک‌های دریافتی موعد گذشته (وصول نشده):** {$cheques['receivable_overdue_count']} فقره به مبلغ `{$recOver}` ریال\n";
            $reply .= "- **چک‌های برگشتی نیازمند پیگیری:** {$cheques['receivable_bounced_count']} فقره به مبلغ `{$recBounced}` ریال\n";
            $reply .= "- **چک‌های پرداختی خودتان که باید پاس شوند:** {$cheques['payable_due_soon_count']} فقره به مبلغ `{$payDue}` ریال\n\n";

            if ($cheques['payable_due_soon_amount'] > $cheques['receivable_due_soon_amount']) {
                $reply .= '⚠️ **هشدار نقدینگی:** مجموع چک‌های پرداختی سررسید این هفته بیشتر از چک‌های دریافتی است. لطفاً نسبت به تامین موجودی حساب‌های جاری خود اطمینان حاصل کنید.';
            } else {
                $reply .= '🟢 **پیش‌بینی خزانه‌داری:** ورود چک‌های دریافتی این هفته توان پوشش تعهدات پرداختی شما را داراست.';
            }

            return [
                'reply' => $reply,
                'tool_calls' => $toolsUsed,
                'sources' => $sources,
            ];
        }

        // -------------------------------------------------------------
        // 4. Tax & Moadian Intent
        // -------------------------------------------------------------
        if ($this->matchesKeywords($normalized, ['مالیات', 'ارزش افزوده', 'مودیان', 'سامانه مودیان', 'ممیزی', 'اظهارنامه', 'آمادگی مالیاتی', 'ریسک مالیات'])) {
            $toolsUsed[] = 'tax_readiness';
            $sources[] = ['title' => 'میز کار آمادگی مالیاتی', 'url' => route('tax-readiness.index')];
            $tax = $this->knowledgeService->getTaxReadiness($companyId);

            $badge = match ($tax['status']) {
                'excellent' => '🟢 عالی',
                'good' => '🟢 خوب',
                'warning' => '🟡 نیازمند توجه',
                default => '🔴 بحرانی',
            };

            $reply = "### ⚖️ وضعیت سلامت و آمادگی مالیاتی شرکت\n\n";
            $reply .= "نمره آمادگی مالیاتی شما: **{$tax['score']} از ۱۰۰** ({$badge})\n\n";

            if (! empty($tax['recommendations'])) {
                $reply .= "#### 🔍 موارد نیازمند اقدام فوری:\n";
                foreach ($tax['recommendations'] as $rec) {
                    $reply .= "- {$rec}\n";
                }
            } else {
                $reply .= '✅ تمامی فاکتورهای مشمول به سامانه مودیان ارسال شده و مستندات مثبته هزینه‌ها کامل می‌باشد.';
            }

            $reply .= "\n📄 جهت آماده‌سازی پرونده رسیدگی ممیز، می‌توانید از بخش [دریافت بسته ممیزی مالیاتی (Audit Pack)](".route('tax-center.audit-pack').') استفاده نمایید.';

            return [
                'reply' => $reply,
                'tool_calls' => $toolsUsed,
                'sources' => $sources,
            ];
        }

        // -------------------------------------------------------------
        // 5. Inventory & Stock Intent
        // -------------------------------------------------------------
        if ($this->matchesKeywords($normalized, ['انبار', 'موجودی', 'کالا', 'کسری', 'اتمام', 'سفارش کالا', 'موجودی انبار'])) {
            $toolsUsed[] = 'low_stock_items';
            $sources[] = ['title' => 'داشبورد انبار', 'url' => route('warehouse.dashboard')];
            $lowStock = $this->knowledgeService->getLowStockProducts(5);

            $reply = "### 📦 وضعیت موجودی کالاهای انبار\n\n";

            if (empty($lowStock)) {
                $reply .= '🟢 موجودی تمامی کالاهای تعریف‌شده بالاتر از حداقل نقطه سفارش است و هیچ کالایی در آستانه کسری قرار ندارد.';
            } else {
                $reply .= "⚠️ کالاهای زیر به حداقل نقطه سفارش رسیده یا کسری دارند:\n\n";
                $reply .= "| کد کالا | نام محصول | موجودی فعلی | حداقل سفارش |\n";
                $reply .= "| :---: | :--- | :---: | :---: |\n";
                foreach ($lowStock as $item) {
                    $reply .= "| `{$item['code']}` | **{$item['name']}** | {$item['current_stock']} | {$item['min_order']} |\n";
                }
                $reply .= "\n🛒 برای شارژ مجدد می‌توانید از بخش [صدور فاکتور خرید جدید](".route('invoices.create', ['type' => 'buy']).') اقدام کنید.';
            }

            return [
                'reply' => $reply,
                'tool_calls' => $toolsUsed,
                'sources' => $sources,
            ];
        }

        // -------------------------------------------------------------
        // 6. Bank & Cash Balances Intent
        // -------------------------------------------------------------
        if ($this->matchesKeywords($normalized, ['بانک', 'حساب بانکی', 'نقدینگی', 'موجودی بانک', 'موجودی صندوق', 'صندوق'])) {
            $toolsUsed[] = 'bank_balances';
            $sources[] = ['title' => 'حساب‌های بانکی', 'url' => route('bank-accounts.index')];
            $banks = $this->knowledgeService->getBankBalances();

            $total = number_format($banks['total_balance']);
            $reply = "### 🏦 موجودی و مانده حساب‌های بانکی\n\n";
            $reply .= "مجموع نقدینگی اولیه ثبت‌شده در حساب‌ها: **`{$total}` ریال**\n\n";

            if (! empty($banks['accounts'])) {
                foreach ($banks['accounts'] as $b) {
                    $bal = number_format($b['balance']);
                    $reply .= "- **{$b['bank']} ({$b['name']}):** `{$bal}` ریال (شماره حساب: `{$b['account_number']}`)\n";
                }
            }

            $reply .= "\n🔄 جهت تطبیق مبالغ دفتر با گردش واقعی حساب، به صفحه [مغایرت‌گیری بانکی](".route('bank-accounts.index').') مراجعه کنید.';

            return [
                'reply' => $reply,
                'tool_calls' => $toolsUsed,
                'sources' => $sources,
            ];
        }

        // -------------------------------------------------------------
        // Default / Help Intent
        // -------------------------------------------------------------
        $reply = "سلام! من **دستیار مالی هوشمند دخل** هستم.\n\n";
        $reply .= "می‌توانید امور حسابداری و ثبت اسناد خود را با زبان محاوره‌ای به من بسپارید؛ به عنوان نمونه:\n\n";
        $reply .= "- 💸 *«۲۰۰ تومن دادم علی»* یا *«۳۰۰ تومن از حسن گرفتم»*\n";
        $reply .= "- 🔄 *«۲ تا کاور دادم رضا»* یا *«۵ تا گلس از رضا گرفتم»*\n";
        $reply .= "- 🏷️ *«به علی قیمت گلس ۴۰ تومن دادم ثبت کن»*\n";
        $reply .= "- ❓ *«به علی قیمت گلس چند دادم؟»*\n";
        $reply .= "- 📊 *«فروش امروز و دخل این هفته چقدر بوده؟»*\n";
        $reply .= "- 👥 *«کدام مشتری‌ها بیشترین طلب رو ازشون داریم؟»*\n\n";
        $reply .= 'برای شروع، دستور یا سؤال خود را تایپ فرمایید.';

        return [
            'reply' => $reply,
            'tool_calls' => ['help_prompt'],
            'sources' => [],
        ];
    }

    // =========================================================================
    // Helper Methods: Intent Detectors & Action Handlers
    // =========================================================================

    public function isActionOrQuoteQuery(string $query): bool
    {
        $normalized = mb_strtolower(trim($query));
        $normalizedDigits = $this->normalizeDigits($normalized);

        return $this->isPriceQuoteQueryIntent($normalized)
            || $this->isPriceQuoteStoreIntent($normalizedDigits)
            || $this->isPeerTradeIntent($normalizedDigits)
            || $this->isConversationalPaymentIntent($normalizedDigits);
    }

    protected function isPriceQuoteQueryIntent(string $text): bool
    {
        return (str_contains($text, 'قیمت') || str_contains($text, 'نرخ') || str_contains($text, 'استعلام'))
            && (str_contains($text, 'چند دادم') || str_contains($text, 'چقدر دادم') || str_contains($text, 'چی گفتم') || str_contains($text, 'چند گفتم') || str_contains($text, 'استعلام قیمت'));
    }

    protected function handlePriceQuoteRecall(int $companyId, string $text): array
    {
        // Try to extract customer name and product
        // e.g. "به علی قیمت گلس چند دادم؟"
        $customerName = null;
        $productName = null;

        if (preg_match('/(?:به|واسه)\s+([\p{L}\s]+?)\s+(?:قیمت|نرخ)\s+([\p{L}\s]+?)\s+(?:چند|چقدر)/u', $text, $matches)) {
            $customerName = trim($matches[1]);
            $productName = trim($matches[2]);
        } elseif (preg_match('/(?:قیمت|نرخ)\s+([\p{L}\s]+?)\s+(?:به|واسه)\s+([\p{L}\s]+?)\s+(?:چند|چقدر)/u', $text, $matches)) {
            $productName = trim($matches[1]);
            $customerName = trim($matches[2]);
        }

        $quotes = $this->priceQuoteService->searchQuotes($companyId, $customerName, $productName);

        if ($quotes->isEmpty()) {
            $targetDesc = trim("{$customerName} برای {$productName}");
            $reply = "🔍 در دفترچه استعلام‌ها، قیمتی برای **{$targetDesc}** یافت نشد.\n\n";
            $reply .= "💡 **نکته:** هر زمان قیمتی اعلام کردید، می‌توانید بگویید: *«به {$customerName} قیمت {$productName} رو ۴۰ تومن دادم ثبت کن»* تا در حافظه سیستم ذخیره گردد.";

            return [
                'reply' => $reply,
                'tool_calls' => ['price_quote_search_empty'],
                'sources' => [['title' => 'دفترچه استعلام قیمت‌ها', 'url' => route('price-quotes.index')]],
            ];
        }

        $latest = $quotes->first();
        $reply = "### 🏷️ آخرین قیمت اعلام‌شده در حافظه سیستم:\n\n";
        $reply .= "- **طرف‌حساب:** {$latest->customer_name}\n";
        $reply .= "- **کالای مورد استعلام:** {$latest->product_name}\n";
        $reply .= "- **نرخ اعلامی شما:** **{$latest->formatted_price}**\n";
        $reply .= '- **تاریخ اعلام:** '.($latest->created_at ? $latest->created_at->format('Y/m/d H:i') : 'امروز')."\n";

        if ($latest->notes) {
            $reply .= "- **یادداشت:** {$latest->notes}\n";
        }

        if ($quotes->count() > 1) {
            $reply .= "\n📋 **سایر قیمت‌های ثبت‌شده در سابقه:**\n";
            foreach ($quotes->skip(1)->take(3) as $q) {
                $reply .= "- به {$q->customer_name} بابت {$q->product_name}: `{$q->formatted_price}`\n";
            }
        }

        return [
            'reply' => $reply,
            'tool_calls' => ['price_quote_search_hit'],
            'sources' => [['title' => 'دفترچه استعلام قیمت‌ها', 'url' => route('price-quotes.index')]],
            'action_card' => [
                'type' => 'price_quote_view',
                'title' => 'استعلام قیمت معتبر در حافظه',
                'details' => [
                    'طرف‌حساب' => $latest->customer_name,
                    'کالا' => $latest->product_name,
                    'قیمت اعلامی' => $latest->formatted_price,
                ],
            ],
        ];
    }

    protected function isPriceQuoteStoreIntent(string $text): bool
    {
        return (str_contains($text, 'قیمت') || str_contains($text, 'نرخ'))
            && (str_contains($text, 'ثبت کن') || str_contains($text, 'یادداشت کن') || str_contains($text, 'به خاطر بسپار') || str_contains($text, 'ذخیره کن'));
    }

    protected function handlePriceQuoteStore(int $companyId, string $text): array
    {
        // e.g. "به علی قیمت گلس رو ۴۰ تومن دادم ثبت کن"
        // or "به محمد قیمت گلس ۴۸ تومن دادم ثبت کن"
        $customerName = 'مشتری';
        $productName = 'کالا';
        $price = 0;

        if (preg_match('/(?:به|واسه)\s+([\p{L}\s]+?)\s+(?:قیمت|نرخ)\s+([\p{L}\s]+?)\s+(?:رو|را)?\s*(\d+)\s*(?:تومن|تومان|هزار|ریال)?/u', $text, $matches)) {
            $customerName = trim($matches[1]);
            $productName = trim($matches[2]);
            $price = (float) $matches[3];
        }

        if ($price < 1000 && (str_contains($text, 'تومن') || str_contains($text, 'تومان'))) {
            $price = $price * 1000; // e.g. 40 تومن -> 40,000 تومن
        }

        $quote = $this->priceQuoteService->recordQuote(
            companyId: $companyId,
            userId: auth()->id(),
            customerName: $customerName,
            productName: $productName,
            quotedPrice: $price,
            currency: str_contains($text, 'ریال') ? 'rial' : 'toman',
            notes: 'ثبت خودکار از طریق گفتگوی هوش مصنوعی دخل'
        );

        $reply = "✅ **قیمت اعلام‌شده در حافظه ثبت گردید!**\n\n";
        $reply .= "- 👤 **طرف‌حساب:** {$customerName}\n";
        $reply .= "- 📦 **کالا:** {$productName}\n";
        $reply .= '- 💰 **نرخ ذخیره‌شده:** **'.number_format($price).' '.($quote->currency === 'rial' ? 'ریال' : 'تومان')."**\n\n";
        $reply .= "💡 زین پس با پرسیدن *«به {$customerName} قیمت {$productName} چند دادم؟»* بلافاصله این نرخ به شما یادآوری خواهد شد.";

        return [
            'reply' => $reply,
            'tool_calls' => ['price_quote_recorded'],
            'sources' => [['title' => 'مشاهده دفترچه استعلام‌ها', 'url' => route('price-quotes.index')]],
            'action_card' => [
                'type' => 'price_quote_saved',
                'title' => 'استعلام قیمت ثبت شد',
                'details' => [
                    'طرف‌حساب' => $customerName,
                    'کالا' => $productName,
                    'مبلغ' => number_format($price).' تومان',
                ],
            ],
        ];
    }

    protected function isPeerTradeIntent(string $text): bool
    {
        // Must contain a barter or physical item keyword
        $hasItem = str_contains($text, 'کاور')
            || str_contains($text, 'گلس')
            || str_contains($text, 'جنس')
            || str_contains($text, 'قرض')
            || str_contains($text, 'تهاتر')
            || str_contains($text, 'امانت')
            || str_contains($text, 'کالا')
            || str_contains($text, 'تا ');

        $hasTradeAction = str_contains($text, 'برگردوند')
            || str_contains($text, 'پس داد')
            || str_contains($text, 'پس دادم')
            || str_contains($text, 'برگردوندم')
            || str_contains($text, 'دادم')
            || str_contains($text, 'گرفتم');

        return $hasItem && $hasTradeAction;
    }

    protected function handlePeerTrade(int $companyId, string $text): array
    {
        // 1. Detect Return (e.g. "رضا ۱ کاور برگردوند" or "۱ کاور پس دادم")
        if (str_contains($text, 'برگردوند') || str_contains($text, 'پس داد')) {
            preg_match('/(\d+)\s*(?:تا|عدد)?\s*([\p{L}\s]+?)\s*(?:برگردوند|پس داد)/u', $text, $m);
            $qty = isset($m[1]) ? (float) $m[1] : 1;
            $item = isset($m[2]) ? trim($m[2]) : 'کالا';

            preg_match('/([\p{L}]+)\s+\d+/u', $text, $mPerson);
            $person = isset($mPerson[1]) ? trim($mPerson[1]) : 'همکار';

            $reply = "### 🔄 ثبت عودت و مرجوعی کالا در کاردکس تبادل همکار\n\n";
            $reply .= "مرجوع شدن **{$qty} عدد {$item}** شناسایی گردید.\n";
            $reply .= 'این ردیف موجب کسر از مانده تعهدات جنسی طرفین خواهد شد.';

            return [
                'reply' => $reply,
                'tool_calls' => ['trade_return_parsed'],
                'sources' => [['title' => 'میز کار تهاتر همکاران', 'url' => route('trades.index')]],
                'action_card' => [
                    'type' => 'trade_return',
                    'title' => 'کارت ثبت مرجوعی تبادل همکار',
                    'details' => [
                        'طرف‌حساب' => $person,
                        'کالای مرجوعی' => $item,
                        'تعداد' => "{$qty} عدد",
                        'اثر' => 'کاهش تعهد امانی',
                    ],
                    'action_url' => route('trades.index'),
                    'button_text' => 'مشاهده و ثبت در پرونده تبادل',
                ],
            ];
        }

        // 2. Detect OUT (e.g. "۲ تا کاور دادم رضا")
        $isOut = str_contains($text, 'دادم') || str_contains($text, 'تحویل دادم');
        $direction = $isOut ? 'out' : 'in';
        $directionTitle = $isOut ? 'تحویل داده شده به همکار (خروج / امانی)' : 'تحویل گرفته شده از همکار (ورود / دریافت)';

        $qty = 1;
        $item = 'کالا';
        $person = 'همکار';

        if (preg_match('/(\d+)\s*(?:تا|عدد)?\s*([\p{L}\s]+?)\s+(?:دادم|تحویل دادم|فرستادم)\s*(?:به)?\s*([\p{L}\s]+)/u', $text, $m)) {
            $qty = (float) $m[1];
            $item = trim($m[2]);
            $person = trim($m[3]);
        } elseif (preg_match('/(\d+)\s*(?:تا|عدد)?\s*([\p{L}\s]+?)\s+(?:از)\s*([\p{L}\s]+)\s*(?:گرفتم|تحویل گرفتم)/u', $text, $m)) {
            $qty = (float) $m[1];
            $item = trim($m[2]);
            $person = trim($m[3]);
        }

        $reply = "### 🔄 پرونده تبادل و تهاتر اقلام با همکار ({$person})\n\n";
        $reply .= "دستور عملیات: **{$qty} عدد {$item}** به صورت **{$directionTitle}** شناسایی شد.\n\n";
        $reply .= "این عملیات فاکتور قطعی و بار مالیاتی برای شما ثبت نمی‌کند، بلکه در **موقعیت باز جنسی تبادل** قرار می‌گیرد تا در پایان روز یا پس از عودت، مانده ریالی/جنسی آن تسویه گردد.\n\n";
        $reply .= 'جهت ثبت نهایی در کاردکس، کارت تایید زیر را تایید فرمایید:';

        return [
            'reply' => $reply,
            'tool_calls' => ['trade_exchange_detected'],
            'sources' => [['title' => 'میز کار تهاتر اقلام', 'url' => route('trades.index')]],
            'action_card' => [
                'type' => 'trade_exchange_leg',
                'title' => 'کارت تبادل کالا با مغازه همکار',
                'details' => [
                    'همکار / مغازه' => $person,
                    'عنوان کالا' => $item,
                    'تعداد' => "{$qty} عدد",
                    'جهت عملیات' => $directionTitle,
                ],
                'action_url' => route('trades.create'),
                'button_text' => 'تایید و ثبت ردیف تبادل',
            ],
        ];
    }

    protected function isConversationalPaymentIntent(string $text): bool
    {
        return (str_contains($text, 'دادم') || str_contains($text, 'واریز کردم') || str_contains($text, 'گرفتم') || str_contains($text, 'پرداخت') || str_contains($text, 'دریافت'))
            && (str_contains($text, 'تومن') || str_contains($text, 'تومان') || str_contains($text, 'ریال') || preg_match('/\d+/', $text));
    }

    protected function handleConversationalPayment(int $companyId, string $text): array
    {
        $isReceive = str_contains($text, 'گرفتم') || str_contains($text, 'دریافت') || str_contains($text, 'واریز کرد');
        $type = $isReceive ? 'receive' : 'pay';
        $typeLabel = $isReceive ? 'دریافت وجه (تسویه بدهی مشتری)' : 'پرداخت وجه (بستانکار کردن طرف‌حساب)';

        $amount = 0;
        $person = 'طرف‌حساب';

        if (preg_match('/(\d+)\s*(?:تومن|تومان|هزار|ریال)?\s*(?:دادم|واریز کردم|ریختم|پرداخت کردم)\s*(?:به)?\s*([\p{L}\s]+)/u', $text, $m)) {
            $amount = (float) $m[1];
            $person = trim($m[2]);
        } elseif (preg_match('/(\d+)\s*(?:تومن|تومان|هزار|ریال)?\s*(?:از)\s*([\p{L}\s]+)\s*(?:گرفتم|دریافت شد)/u', $text, $m)) {
            $amount = (float) $m[1];
            $person = trim($m[2]);
        } elseif (preg_match('/(?:به|واسه)\s*([\p{L}\s]+?)\s*(\d+)\s*(?:دادم|پرداخت کردم)/u', $text, $m)) {
            $person = trim($m[1]);
            $amount = (float) $m[2];
        }

        if ($amount < 1000 && (str_contains($text, 'تومن') || str_contains($text, 'تومان'))) {
            $amount = $amount * 1000;
        }

        $rialAmount = $amount * 10; // Toman to Rial

        $reply = "### 💳 کارت عملیات مالی: {$typeLabel}\n\n";
        $reply .= "مشخصات استخراج‌شده از پیام شما:\n";
        $reply .= "- 👤 **طرف‌حساب:** {$person}\n";
        $reply .= '- 💰 **مبلغ:** **'.number_format($amount).' تومان** ('.number_format($rialAmount)." ریال)\n\n";
        $reply .= '⚠️ **قاعده ایمنی حسابداری دخل:** برای جلوگیری از ثبت اشتباه سند مالی، سندها مستقیماً توسط چت‌بات بدون تایید شما قطعی نمی‌شوند. با لمس دکمه زیر، سند رسمی بلافاصله ثبت خواهد شد:';

        return [
            'reply' => $reply,
            'tool_calls' => ['conversational_payment_action_card'],
            'sources' => [['title' => 'پرداخت / دریافت سریع', 'url' => route('quick-payment.create', ['direction' => 'receive'])]],
            'action_card' => [
                'type' => 'quick_payment',
                'title' => $typeLabel,
                'details' => [
                    'طرف‌حساب' => $person,
                    'مبلغ به تومان' => number_format($amount).' تومان',
                    'مبلغ به ریال' => number_format($rialAmount).' ریال',
                    'نوع عملیات' => $isReceive ? 'ورود به صندوق / بانک' : 'خروج از حساب',
                ],
                'action_url' => route('quick-payment.create', ['direction' => $type]),
                'button_text' => 'تایید و صدور قطعی سند حسابداری',
            ],
        ];
    }

    private function normalizeDigits(string $text): string
    {
        return str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $text
        );
    }

    private function matchesKeywords(string $text, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (mb_strpos($text, $kw) !== false) {
                return true;
            }
        }

        return false;
    }
}
