<?php

namespace App\Services\AiCopilot;

/**
 * Deterministic Financial Chat Engine.
 * Provides instant, zero-cost, privacy-safe, fully offline responses in Persian
 * by parsing financial intents and composing verified facts from FinancialKnowledgeService.
 */
class DeterministicFinancialChatEngine
{
    public function __construct(
        protected FinancialKnowledgeService $knowledgeService
    ) {}

    public function answer(int $companyId, string $query): array
    {
        $normalized = mb_strtolower(trim($query));
        $toolsUsed = [];
        $sources = [];

        // 1. Sales & Revenue Intent
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
                $reply .= "💡 **تحلیل دستیار:** تراز عملیاتی ماهانه مثبت است و ورودی فاکتورهای فروش نسبت به خریدها برتری دارد. پیشنهاد می‌شود جهت وصول به موقع مطالبات ناشی از این فروش، لینک‌های پرداخت آنلاین را برای مشتریان فعال کنید.";
            } else {
                $reply .= "⚠️ **تحلیل دستیار:** حجم خریدهای این ماه نسبت به فروش بالاتر است؛ این امر در صورت انباشت موجودی انبار طبیعی است اما جریان نقدینگی کوتاه‌مدت را نیازمند مراقبت می‌کند.";
            }

            return [
                'reply' => $reply,
                'tool_calls' => $toolsUsed,
                'sources' => $sources,
            ];
        }

        // 2. Debtors & Overdue Receivables Intent
        if ($this->matchesKeywords($normalized, ['بدهکار', 'طلب', 'مطالبات', 'معوق', 'چه کسانی بدهکارن', 'بدهی مشتری', 'وصول'])) {
            $toolsUsed[] = 'ageing_debtors';
            $sources[] = ['title' => 'گزارش سنی مطالبات', 'url' => route('ageing.receivables')];
            $debtors = $this->knowledgeService->getTopOverdueDebtors(5);

            $total = number_format($debtors['total_receivables']);
            $reply = "### 👥 وضعیت مطالبات و مشتریان بدهکار\n\n";
            $reply .= "مجموع مطالبات باز شما از مشتریان: **`{$total}` ریال** است.\n\n";

            if (empty($debtors['top_debtors'])) {
                $reply .= "🟢 خوشبختانه در حال حاضر هیچ مطالبه معوق با ریسک بالا ثبت نشده است و حساب مشتریان به‌روز می‌باشد.";
            } else {
                $reply .= "| نام مشتری | مانده کل بدهی (ریال) | بدهی بالای ۹۰ روز | فاکتورهای باز |\n";
                $reply .= "| :--- | :---: | :---: | :---: |\n";
                foreach ($debtors['top_debtors'] as $d) {
                    $dTotal = number_format($d['total_debt']);
                    $dOver = number_format($d['over_90_days']);
                    $reply .= "| **{$d['name']}** | {$dTotal} | {$dOver} | {$d['invoices_count']} فاکتور |\n";
                }

                $reply .= "\n📌 **اقدام پیشنهادی:** برای مشتریانی که بدهی بالای ۹۰ روز دارند، می‌توانید از بخش [ارسال پیامک یادآوری هوشمند](" . route('sms.index') . ") یا صدور [لینک پرداخت شاپرک](" . route('payment-links.index') . ") جهت وصول سریع‌تر استفاده نمایید.";
            }

            return [
                'reply' => $reply,
                'tool_calls' => $toolsUsed,
                'sources' => $sources,
            ];
        }

        // 3. Cheques & Treasury Intent
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
                $reply .= "⚠️ **هشدار نقدینگی:** مجموع چک‌های پرداختی سررسید این هفته بیشتر از چک‌های دریافتی است. لطفاً نسبت به تامین موجودی حساب‌های جاری خود اطمینان حاصل کنید.";
            } else {
                $reply .= "🟢 **پیش‌بینی خزانه‌داری:** ورود چک‌های دریافتی این هفته توان پوشش تعهدات پرداختی شما را داراست.";
            }

            return [
                'reply' => $reply,
                'tool_calls' => $toolsUsed,
                'sources' => $sources,
            ];
        }

        // 4. Tax & Moadian Intent
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

            if (!empty($tax['recommendations'])) {
                $reply .= "#### 🔍 موارد نیازمند اقدام فوری:\n";
                foreach ($tax['recommendations'] as $rec) {
                    $reply .= "- {$rec}\n";
                }
            } else {
                $reply .= "✅ تمامی فاکتورهای مشمول به سامانه مودیان ارسال شده و مستندات مثبته هزینه‌ها کامل می‌باشد.";
            }

            $reply .= "\n📄 جهت آماده‌سازی پرونده رسیدگی ممیز، می‌توانید از بخش [دریافت بسته ممیزی مالیاتی (Audit Pack)](" . route('tax-center.audit-pack') . ") استفاده نمایید.";

            return [
                'reply' => $reply,
                'tool_calls' => $toolsUsed,
                'sources' => $sources,
            ];
        }

        // 5. Inventory & Stock Intent
        if ($this->matchesKeywords($normalized, ['انبار', 'موجودی', 'کالا', 'کسری', 'اتمام', 'سفارش کالا', 'موجودی انبار'])) {
            $toolsUsed[] = 'low_stock_items';
            $sources[] = ['title' => 'داشبورد انبار', 'url' => route('warehouse.dashboard')];
            $lowStock = $this->knowledgeService->getLowStockProducts(5);

            $reply = "### 📦 وضعیت موجودی کالاهای انبار\n\n";

            if (empty($lowStock)) {
                $reply .= "🟢 موجودی تمامی کالاهای تعریف‌شده بالاتر از حداقل نقطه سفارش است و هیچ کالایی در آستانه کسری قرار ندارد.";
            } else {
                $reply .= "⚠️ کالاهای زیر به حداقل نقطه سفارش رسیده یا کسری دارند:\n\n";
                $reply .= "| کد کالا | نام محصول | موجودی فعلی | حداقل سفارش |\n";
                $reply .= "| :---: | :--- | :---: | :---: |\n";
                foreach ($lowStock as $item) {
                    $reply .= "| `{$item['code']}` | **{$item['name']}** | {$item['current_stock']} | {$item['min_order']} |\n";
                }
                $reply .= "\n🛒 برای شارژ مجدد می‌توانید از بخش [صدور فاکتور خرید جدید](" . route('invoices.create', ['type' => 'buy']) . ") اقدام کنید.";
            }

            return [
                'reply' => $reply,
                'tool_calls' => $toolsUsed,
                'sources' => $sources,
            ];
        }

        // 6. Bank & Cash Balances Intent
        if ($this->matchesKeywords($normalized, ['بانک', 'حساب بانکی', 'نقدینگی', 'موجودی بانک', 'موجودی صندوق', 'صندوق'])) {
            $toolsUsed[] = 'bank_balances';
            $sources[] = ['title' => 'حساب‌های بانکی', 'url' => route('bank-accounts.index')];
            $banks = $this->knowledgeService->getBankBalances();

            $total = number_format($banks['total_balance']);
            $reply = "### 🏦 موجودی و مانده حساب‌های بانکی\n\n";
            $reply .= "مجموع نقدینگی اولیه ثبت‌شده در حساب‌ها: **`{$total}` ریال**\n\n";

            if (!empty($banks['accounts'])) {
                foreach ($banks['accounts'] as $b) {
                    $bal = number_format($b['balance']);
                    $reply .= "- **{$b['bank']} ({$b['name']}):** `{$bal}` ریال (شماره حساب: `{$b['account_number']}`)\n";
                }
            }

            $reply .= "\n🔄 جهت تطبیق مبالغ دفتر با گردش واقعی حساب، به صفحه [مغایرت‌گیری بانکی](" . route('bank-accounts.index') . ") مراجعه کنید.";

            return [
                'reply' => $reply,
                'tool_calls' => $toolsUsed,
                'sources' => $sources,
            ];
        }

        // 7. General Business Performance Intent
        if ($this->matchesKeywords($normalized, ['وضعیت', 'اوضاع', 'چطور', 'خلاصه', 'گزارش کلی', 'عملکرد', 'سود', 'کسب و کار'])) {
            $toolsUsed[] = ['sales_summary', 'ageing_debtors', 'cheques_report', 'tax_readiness'];
            $sources[] = ['title' => 'داشبورد امروز من', 'url' => route('home.today')];

            $sales = $this->knowledgeService->getSalesSummary($companyId);
            $debtors = $this->knowledgeService->getTopOverdueDebtors(3);
            $cheques = $this->knowledgeService->getChequesDueSoon(7);
            $tax = $this->knowledgeService->getTaxReadiness($companyId);

            $salesMonth = number_format($sales['sales_this_month']);
            $totalDebt = number_format($debtors['total_receivables']);
            $chequeDue = number_format($cheques['receivable_due_soon_amount']);

            $reply = "### 🧭 تصویر کلی از وضعیت کسب‌وکار شما در دخل\n\n";
            $reply .= "- **فروش ماه جاری:** `{$salesMonth}` ریال\n";
            $reply .= "- **کل مطالبات از مشتریان:** `{$totalDebt}` ریال\n";
            $reply .= "- **ورودی چک‌های این هفته:** `{$chequeDue}` ریال ({$cheques['receivable_due_soon_count']} فقره)\n";
            $reply .= "- **شاخص آمادگی مالیاتی:** `{$tax['score']}/۱۰۰`\n\n";
            $reply .= "💡 **توصیه راهبردی:** برای حفظ چرخه مثبت نقدینگی، بر وصول حساب‌های بالای ۳۰ روز تمرکز کرده و ارسال روزانه فاکتورها به سامانه مودیان را در موعد خود نهایی کنید.";

            return [
                'reply' => $reply,
                'tool_calls' => $toolsUsed,
                'sources' => $sources,
            ];
        }

        // Default / Help Intent
        $reply = "سلام! من **دستیار مالی هوشمند دخل** هستم.\n\n";
        $reply .= "می‌توانید هر سؤالی در رابطه با حساب‌ها، فاکتورها، مشتریان و مالیات خود بپرسید؛ به عنوان نمونه:\n\n";
        $reply .= "- 💰 *«فروش امروز و این هفته من چقدر بوده؟»*\n";
        $reply .= "- 👥 *«کدام مشتریان بیشترین بدهی معوق رو دارن؟»*\n";
        $reply .= "- 💳 *«چک‌های سررسید این هفته چیا هستن؟»*\n";
        $reply .= "- ⚖️ *«وضعیت آمادگی مالیاتی و سامانه مودیان من چطوره؟»*\n";
        $reply .= "- 📦 *«موجودی کدام کالاها رو به اتمامه؟»*\n";
        $reply .= "- 🏦 *«موجودی حساب‌های بانکی من چقدره؟»*\n\n";
        $reply .= "برای شروع، یکی از موضوعات بالا را تایپ کرده یا کلیک نمایید.";

        return [
            'reply' => $reply,
            'tool_calls' => ['help_prompt'],
            'sources' => [],
        ];
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
