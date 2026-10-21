<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CopilotConversation;
use App\Models\CopilotMessage;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\AiCopilot\FinancialCopilotService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FinancialCopilotTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected FinancialCopilotService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::firstOrCreate(['id' => 1], [
            'name' => 'Dakhl Copilot Test Co',
            'fiscal_year' => 1405,
        ]);

        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);

        Cache::forever('active_company_id', $this->company->id);
        Cookie::queue('active-company-id', (string) $this->company->id);
        $_COOKIE['active-company-id'] = (string) $this->company->id;
        config(['active-company-id' => $this->company->id]);

        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'copilot.chat']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'copilot.index']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'copilot.destroy']));

        $this->service = app(FinancialCopilotService::class);
    }

    public function test_unauthenticated_user_cannot_access_copilot(): void
    {
        $this->get(route('copilot.index'))->assertRedirect(route('login'));
        $this->postJson(route('copilot.chat'), ['message' => 'سلام'])->assertUnauthorized();
    }

    public function test_user_without_permission_receives_403(): void
    {
        $unauthorizedUser = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($unauthorizedUser);

        $this->actingAs($unauthorizedUser)
            ->get(route('copilot.index'))
            ->assertForbidden();

        $this->actingAs($unauthorizedUser)
            ->postJson(route('copilot.chat'), ['message' => 'سلام'])
            ->assertForbidden();
    }

    public function test_permitted_user_can_view_copilot_index(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('copilot.index'));

        $response->assertOk()
            ->assertSee(__('دستیار مالی و اقتصادی دخل (Dakhl Copilot)'))
            ->assertSee(__('گفتگوی جدید'));
    }

    public function test_deterministic_sales_intent_returns_grounded_response(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('copilot.chat'), [
                'message' => 'میزان فروش امروز و این هفته من چقدر بوده؟',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'conversation_id',
                'title',
                'user_message',
                'assistant_message' => [
                    'content',
                    'tool_calls',
                    'sources',
                ],
            ]);

        $content = $response->json('assistant_message.content');
        $this->assertStringContainsString('گزارش عملکرد فروش و درآمد شما', $content);
        $this->assertStringContainsString('فروش امروز', $content);
    }

    public function test_deterministic_debtors_intent_returns_grounded_response(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('copilot.chat'), [
                'message' => 'کدام مشتریان بدهکار هستند و بیشترین طلب معوق را دارند؟',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $content = $response->json('assistant_message.content');
        $this->assertStringContainsString('وضعیت مطالبات و مشتریان بدهکار', $content);
    }

    public function test_deterministic_cheques_intent_returns_grounded_response(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('copilot.chat'), [
                'message' => 'وضعیت چک‌های سررسید این هفته چطوره؟',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $content = $response->json('assistant_message.content');
        $this->assertStringContainsString('چک‌های دریافتی و پرداختی', $content);
    }

    public function test_deterministic_tax_intent_returns_grounded_response(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('copilot.chat'), [
                'message' => 'آمادگی مالیاتی و وضعیت سامانه مودیان شرکت من چطوره؟',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $content = $response->json('assistant_message.content');
        $this->assertStringContainsString('وضعیت سلامت و آمادگی مالیاتی', $content);
    }

    public function test_deterministic_stock_intent_returns_grounded_response(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('copilot.chat'), [
                'message' => 'موجودی کالاهای انبار که رو به اتمامه رو بگو',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $content = $response->json('assistant_message.content');
        $this->assertStringContainsString('موجودی کالاهای انبار', $content);
    }

    public function test_conversation_thread_and_messages_persisted(): void
    {
        $this->actingAs($this->user);

        // Turn 1
        $r1 = $this->postJson(route('copilot.chat'), [
            'message' => 'سلام راهنمای وضعیت کسب و کار رو بگو',
        ])->assertOk();

        $conversationId = $r1->json('conversation_id');
        $this->assertNotNull($conversationId);

        // Turn 2 in same conversation
        $r2 = $this->postJson(route('copilot.chat'), [
            'message' => 'فروش امروز چقدر بود؟',
            'conversation_id' => $conversationId,
        ])->assertOk();

        $this->assertSame($conversationId, $r2->json('conversation_id'));

        // Assert DB count
        $this->assertSame(1, CopilotConversation::where('id', $conversationId)->count());
        $this->assertSame(4, CopilotMessage::where('conversation_id', $conversationId)->count()); // 2 user + 2 assistant
    }

    public function test_company_isolation_conversations_do_not_leak_across_companies(): void
    {
        $this->actingAs($this->user);

        $convA = CopilotConversation::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'title' => 'Company A Conversation',
        ]);

        $otherCompany = Company::factory()->create(['name' => 'Other Company', 'fiscal_year' => 1405]);
        $otherUser = User::factory()->create();
        $otherCompany->users()->syncWithoutDetaching($otherUser);

        // Switch to other company
        config(['active-company-id' => $otherCompany->id]);
        $otherUser->givePermissionTo(Permission::firstOrCreate(['name' => 'copilot.chat']));

        // Attempting to chat with Company A's conversation ID should create a new conversation under Company B
        $response = $this->actingAs($otherUser)
            ->postJson(route('copilot.chat'), [
                'message' => 'سلام وضعیت فروش',
                'conversation_id' => $convA->id,
            ]);

        $response->assertOk();
        $newConvId = $response->json('conversation_id');
        $this->assertNotSame($convA->id, $newConvId);
        $this->assertSame($otherCompany->id, CopilotConversation::withoutGlobalScopes()->find($newConvId)->company_id);
    }

    public function test_deleting_conversation_works_with_destroy_permission(): void
    {
        $this->actingAs($this->user);

        $conv = CopilotConversation::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'title' => 'Delete Me Conversation',
        ]);

        $this->deleteJson(route('copilot.destroy', $conv))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('copilot_conversations', ['id' => $conv->id]);
    }
}
