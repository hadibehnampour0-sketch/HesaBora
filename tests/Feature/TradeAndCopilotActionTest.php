<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\PriceQuote;
use App\Models\Product;
use App\Models\TradeExchange;
use App\Models\User;
use App\Services\AiCopilot\DeterministicFinancialChatEngine;
use App\Services\PriceQuoteService;
use App\Services\Trade\TradeExchangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class TradeAndCopilotActionTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Company $company;

    protected Customer $counterparty;

    protected TradeExchangeService $tradeService;

    protected PriceQuoteService $quoteService;

    protected DeterministicFinancialChatEngine $chatEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::firstOrCreate(['id' => 1], [
            'name' => 'فروشگاه تست تهاتر',
            'fiscal_year' => 1405,
        ]);

        Cache::forever('active_company_id', $this->company->id);
        Cookie::queue('active-company-id', (string) $this->company->id);
        $_COOKIE['active-company-id'] = (string) $this->company->id;

        $this->importSubjects($this->company->id);
        $this->importConfigs($this->company->id);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'copilot.chat']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'copilot.destroy']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'trades.*']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'price-quotes.*']));
        $this->actingAs($this->user);
        $this->company->users()->syncWithoutDetaching($this->user);

        $this->counterparty = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'رضا (موبایل پارس)',
            'mobile' => '09121112233',
        ]);

        $this->tradeService = app(TradeExchangeService::class);
        $this->quoteService = app(PriceQuoteService::class);
        $this->chatEngine = app(DeterministicFinancialChatEngine::class);
    }

    public function test_trade_exchange_lifecycle_give_take_return_and_settle(): void
    {
        // 1. Create Exchange: gave 2 covers to Reza (OUT), took 5 glasses from Reza (IN)
        $exchange = $this->tradeService->createExchange(
            companyId: $this->company->id,
            customerId: $this->counterparty->id,
            title: 'تبادل روزانه با موبایل رضا',
            createdBy: $this->user->id,
            notes: 'تبادل کاور و گلس',
            items: [
                ['direction' => 'out', 'item_name' => 'کاور سیلیکونی آیفون ۱۳', 'quantity' => 2, 'unit_price' => 2000000],
                ['direction' => 'in', 'item_name' => 'گلس سرامیکی', 'quantity' => 5, 'unit_price' => 500000],
            ]
        );

        $this->assertDatabaseHas('trade_exchanges', [
            'id' => $exchange->id,
            'status' => 'open',
            'customer_id' => $this->counterparty->id,
        ]);
        $this->assertCount(2, $exchange->items);

        // Check initial valuation:
        // OUT value: 2 * 2,000,000 = 4,000,000
        // IN value: 5 * 500,000 = 2,500,000
        // Net: 4,000,000 - 2,500,000 = +1,500,000 (Reza owes us)
        $summary1 = $exchange->calculateValuationSummary();
        $this->assertEquals(4000000, $summary1['total_out_value']);
        $this->assertEquals(2500000, $summary1['total_in_value']);
        $this->assertEquals(1500000, $summary1['net_valuation']);

        // 2. Reza returns 1 cover
        $itemCover = $exchange->itemsOut()->first();
        $this->tradeService->recordReturn($itemCover, 1);
        $this->assertEquals(1, $itemCover->fresh()->returned_quantity);
        $this->assertEquals(1, $itemCover->fresh()->effective_quantity);

        // 3. We return 3 glasses to Reza
        $itemGlass = $exchange->itemsIn()->first();
        $this->tradeService->recordReturn($itemGlass, 3);
        $this->assertEquals(3, $itemGlass->fresh()->returned_quantity);
        $this->assertEquals(2, $itemGlass->fresh()->effective_quantity);

        // New valuation:
        // OUT: (2 - 1) * 2,000,000 = 2,000,000
        // IN: (5 - 3) * 500,000 = 1,000,000
        // Net: 2,000,000 - 1,000,000 = +1,000,000
        $summary2 = $exchange->calculateValuationSummary();
        $this->assertEquals(2000000, $summary2['total_out_value']);
        $this->assertEquals(1000000, $summary2['total_in_value']);
        $this->assertEquals(1000000, $summary2['net_valuation']);

        // 4. Settle the exchange
        $settled = $this->tradeService->settleExchange($exchange, $this->user, 'goods', null, 'صاف شدیم به صورت جنسی');
        $this->assertEquals('settled', $settled->status);
        $this->assertEquals('goods', $settled->settlement_type);
        $this->assertNotNull($settled->settled_at);
    }

    public function test_trade_exchange_settlement_creates_balanced_accounting_document(): void
    {
        $exchange = $this->tradeService->createExchange(
            companyId: $this->company->id,
            customerId: $this->counterparty->id,
            title: 'تهاتر نقدی با موبایل رضا',
            createdBy: $this->user->id,
            items: [
                ['direction' => 'out', 'item_name' => 'لوازم جانبی', 'quantity' => 1, 'unit_price' => 5000000],
                ['direction' => 'in', 'item_name' => 'قطعات تعمیری', 'quantity' => 1, 'unit_price' => 2000000],
            ]
        );

        // Net: 5,000,000 - 2,000,000 = +3,000,000
        $settled = $this->tradeService->settleExchange(
            exchange: $exchange,
            user: $this->user,
            settlementType: 'ledger',
            notes: 'انتقال مانده تهاتر ۳ میلیون ریال به دفتر حساب مشتری'
        );

        $this->assertEquals('settled', $settled->status);
        $this->assertNotNull($settled->document_id);

        $document = $settled->document;
        $this->assertNotNull($document);
        $this->assertNotNull($document->approved_at);
        $this->assertCount(2, $document->transactions);

        // Accounting Invariant: Sum of transactions must be zero!
        $this->assertEquals(0.0, (float) $document->transactions->sum('value'));
    }

    public function test_price_quote_service_and_storage(): void
    {
        // Ali asks for glass price -> told 40,000 Tomans
        $quote1 = $this->quoteService->recordQuote(
            companyId: $this->company->id,
            userId: $this->user->id,
            customerName: 'علی رضایی',
            productName: 'گلس سرامیکی آیفون',
            quotedPrice: 40000,
            currency: 'toman',
            notes: 'استعلام تلفنی'
        );

        // Mohammad asks for glass price -> told 48,000 Tomans
        $quote2 = $this->quoteService->recordQuote(
            companyId: $this->company->id,
            userId: $this->user->id,
            customerName: 'محمد احمدی',
            productName: 'گلس سرامیکی آیفون',
            quotedPrice: 48000,
            currency: 'toman',
            notes: 'مشتری عبوری'
        );

        $this->assertDatabaseHas('price_quotes', ['id' => $quote1->id, 'quoted_price' => 40000]);
        $this->assertDatabaseHas('price_quotes', ['id' => $quote2->id, 'quoted_price' => 48000]);

        // Search quotes for Ali
        $aliQuotes = $this->quoteService->searchQuotes($this->company->id, 'علی');
        $this->assertCount(1, $aliQuotes);
        $this->assertEquals(40000, $aliQuotes->first()->quoted_price);

        // Search quotes for product 'گلس'
        $glassQuotes = $this->quoteService->searchQuotes($this->company->id, null, 'گلس');
        $this->assertCount(2, $glassQuotes);
    }

    public function test_copilot_price_quote_memory_recall_and_store(): void
    {
        // Pre-seed a quote for Ali
        $this->quoteService->recordQuote(
            companyId: $this->company->id,
            userId: $this->user->id,
            customerName: 'علی',
            productName: 'گلس',
            quotedPrice: 40000,
            currency: 'toman'
        );

        // Ask Copilot: "به علی قیمت گلس چند دادم؟"
        $response = $this->chatEngine->answer($this->company->id, 'به علی قیمت گلس چند دادم؟');

        $this->assertStringContainsString('40,000', $response['reply']);
        $this->assertStringContainsString('علی', $response['reply']);
        $this->assertArrayHasKey('action_card', $response);
        $this->assertEquals('price_quote_view', $response['action_card']['type']);

        // Now test conversational store: "به محمد قیمت گلس ۴۸ تومن دادم ثبت کن"
        $storeResponse = $this->chatEngine->answer($this->company->id, 'به محمد قیمت گلس ۴۸ تومن دادم ثبت کن');
        $this->assertStringContainsString('محمد', $storeResponse['reply']);
        $this->assertStringContainsString('48,000', $storeResponse['reply']);

        // Check that it was persisted in database
        $this->assertDatabaseHas('price_quotes', [
            'company_id' => $this->company->id,
            'customer_name' => 'محمد',
            'product_name' => 'گلس',
            'quoted_price' => 48000,
        ]);
    }

    public function test_copilot_conversational_accounting_action_cards(): void
    {
        // 1. Payment Action Card: "۲۰۰ تومن دادم علی"
        $payResponse = $this->chatEngine->answer($this->company->id, '۲۰۰ تومن دادم علی');
        $this->assertArrayHasKey('action_card', $payResponse);
        $this->assertEquals('quick_payment', $payResponse['action_card']['type']);
        $this->assertStringContainsString('علی', $payResponse['action_card']['details']['طرف‌حساب']);
        $this->assertStringContainsString('200,000 تومان', $payResponse['action_card']['details']['مبلغ به تومان']);

        // 2. Receipt Action Card: "۳۰۰ از حسن گرفتم"
        $receiveResponse = $this->chatEngine->answer($this->company->id, '۳۰۰ از حسن گرفتم');
        $this->assertArrayHasKey('action_card', $receiveResponse);
        $this->assertEquals('quick_payment', $receiveResponse['action_card']['type']);
        $this->assertStringContainsString('حسن', $receiveResponse['action_card']['details']['طرف‌حساب']);

        // 3. Trade Action Card: "۲ تا کاور دادم رضا"
        $tradeResponse = $this->chatEngine->answer($this->company->id, '۲ تا کاور دادم رضا');
        $this->assertArrayHasKey('action_card', $tradeResponse);
        $this->assertEquals('trade_exchange_leg', $tradeResponse['action_card']['type']);
        $this->assertStringContainsString('رضا', $tradeResponse['action_card']['details']['همکار / مغازه']);
        $this->assertStringContainsString('2 عدد', $tradeResponse['action_card']['details']['تعداد']);
    }

    public function test_http_routes_for_trade_and_quotes(): void
    {
        // 1. Test Trade Controller Store via HTTP
        $tradePost = $this->post(route('trades.store'), [
            'customer_id' => $this->counterparty->id,
            'title' => 'تبادل تستی وب',
            'items' => [
                ['direction' => 'out', 'item_name' => 'کابل تایپ سی', 'quantity' => 10, 'unit_price' => 800000],
            ],
        ]);
        $tradePost->assertRedirect();
        $this->assertDatabaseHas('trade_exchanges', ['title' => 'تبادل تستی وب']);

        $exchange = TradeExchange::where('title', 'تبادل تستی وب')->first();

        // 2. Add item via HTTP
        $addItem = $this->post(route('trades.items.add', $exchange), [
            'direction' => 'in',
            'item_name' => 'آداپتور فست شارژ',
            'quantity' => 3,
            'unit_price' => 2000000,
        ]);
        $addItem->assertRedirect();
        $this->assertCount(2, $exchange->fresh()->items);

        // 3. Return item via HTTP
        $item = $exchange->items()->where('item_name', 'کابل تایپ سی')->first();
        $returnPost = $this->post(route('trades.items.return', $item), [
            'returned_quantity' => 2,
        ]);
        $returnPost->assertRedirect();
        $this->assertEquals(2, $item->fresh()->returned_quantity);

        // 4. Settle exchange via HTTP
        $settlePost = $this->post(route('trades.settle', $exchange), [
            'settlement_type' => 'goods',
            'notes' => 'تسویه از وب',
        ]);
        $settlePost->assertRedirect();
        $this->assertEquals('settled', $exchange->fresh()->status);

        // 5. Test PriceQuote Controller via HTTP
        $quotePost = $this->post(route('price-quotes.store'), [
            'customer_name' => 'سهراب',
            'product_name' => 'شارژر بی سیم',
            'quoted_price' => 75000,
            'currency' => 'toman',
        ]);
        $quotePost->assertRedirect();
        $this->assertDatabaseHas('price_quotes', ['customer_name' => 'سهراب', 'quoted_price' => 75000]);

        // 6. Test Copilot Chat endpoint returning action card via JSON
        $chatResponse = $this->postJson(route('copilot.chat'), [
            'message' => '۲۰۰ تومن دادم علی',
        ]);
        $chatResponse->assertOk();
        $chatResponse->assertJsonPath('success', true);
        $chatResponse->assertJsonPath('action_card.type', 'quick_payment');
    }
}
