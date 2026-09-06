<?php

namespace Tests\Feature;

use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\TelegramSalesBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramSalesBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.telegram_sales', [
            'enabled' => true,
            'bot_token' => 'test-token',
            'allowed_chat_ids' => ['111', '222'],
            'webhook_secret' => 'test-webhook-secret',
            'timeout' => 10,
        ]);
    }

    #[Test]
    public function completed_sale_notification_is_sent_to_each_allowed_user_with_sales_details(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
        $sale = Sale::factory()->create([
            'sale_number' => 'SAL-TELEGRAM-1',
            'status' => SaleStatus::Completed,
            'sales_channel' => 'website',
            'order_status' => 'pending',
            'payment_status' => 'unpaid',
            'delivery_method' => 'local_delivery',
            'grand_total' => 125,
        ]);
        $product = Product::factory()->create([
            'company_id' => $sale->company_id,
            'name' => 'Test Island Shirt',
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'company_id' => $sale->company_id,
            'product_id' => $product->id,
            'description' => 'Test Island Shirt',
            'quantity' => 2,
            'line_total' => 125,
        ]);

        app(TelegramSalesBotService::class)->notifySale($sale->id);

        Http::assertSentCount(2);
        foreach (['111', '222'] as $chatId) {
            Http::assertSent(fn ($request): bool => $request['chat_id'] === $chatId
                && str_contains($request['text'], 'Website sale — SAL-TELEGRAM-1')
                && str_contains($request['text'], 'Total: MVR 125.00')
                && str_contains($request['text'], 'Delivery: Local delivery')
                && str_contains($request['text'], 'Test Island Shirt')
            );
        }
    }

    #[Test]
    public function webhook_serves_allowed_users_and_ignores_everyone_else(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
        Sale::factory()->create([
            'sale_number' => 'SAL-BOT-LOOKUP',
            'status' => SaleStatus::Completed,
            'grand_total' => 75,
        ]);

        $this->postJson('/api/telegram/webhook', [
            'message' => ['chat' => ['id' => 999], 'from' => ['username' => 'intruder'], 'text' => '/sales'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'])->assertOk();
        Http::assertNothingSent();

        $this->postJson('/api/telegram/webhook', [
            'message' => ['chat' => ['id' => 111], 'from' => ['username' => 'allowed'], 'text' => '/sale SAL-BOT-LOOKUP'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'])->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['chat_id'] === '111'
            && str_contains($request['text'], 'SAL-BOT-LOOKUP')
            && str_contains($request['text'], 'MVR 75.00')
        );
    }

    #[Test]
    public function webhook_rejects_requests_without_the_telegram_secret(): void
    {
        Http::fake();

        $this->postJson('/api/telegram/webhook', [
            'message' => ['chat' => ['id' => 111], 'text' => '/today'],
        ])->assertForbidden();

        Http::assertNothingSent();
    }
}
