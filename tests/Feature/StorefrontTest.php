<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_only_shows_products_enabled_for_online_sale(): void
    {
        $this->seed(DatabaseSeeder::class);
        $online = Product::query()->where('sku', 'GALAXY-A16')->firstOrFail();
        $offline = Product::query()->where('sku', 'COKE-500')->firstOrFail();

        $this->get(route('store.home'))->assertOk()->assertSee($online->name)->assertDontSee($offline->name);
        $this->get(route('store.shop'))->assertOk()->assertSee($online->name)->assertDontSee($offline->name);
        $this->get(route('store.product', $online->slug))->assertOk()->assertSee($online->name);
        $this->get('/products/'.$offline->slug)->assertNotFound();
    }

    public function test_product_page_renders_a_formatted_and_sanitized_description(): void
    {
        $this->seed(DatabaseSeeder::class);
        $product = Product::query()->where('sku', 'GALAXY-A16')->firstOrFail();
        $product->update([
            'description' => '<h2>Built for island life</h2><ul><li><strong>Fast</strong> and dependable</li></ul><script>alert("unsafe")</script>',
        ]);

        $this->get(route('store.product', $product->slug))
            ->assertOk()
            ->assertSee('class="store-rich-text mt-4"', false)
            ->assertSee('<h2>Built for island life</h2>', false)
            ->assertSee('<ul><li><strong>Fast</strong> and dependable</li></ul>', false)
            ->assertDontSee('<script>', false)
            ->assertDontSee('alert("unsafe")', false);
    }

    public function test_guest_checkout_creates_a_website_sale_and_uses_shared_inventory(): void
    {
        $this->seed(DatabaseSeeder::class);
        $product = Product::query()->where('sku', 'GALAXY-A16')->firstOrFail();
        $balance = InventoryBalance::query()->where('product_id', $product->id)->firstOrFail();
        $before = (float) $balance->quantity;

        $this->post(route('store.cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertSessionHasNoErrors();
        $response = $this->post(route('store.checkout.place'), [
            'name' => 'Web Customer',
            'phone' => '7771234',
            'email' => 'customer@example.com',
            'delivery_method' => 'pickup',
            'payment_method' => 'cash',
            'notes' => 'Call before collection',
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirectContains('/order/');

        $order = Sale::query()->where('sales_channel', 'website')->firstOrFail();
        $response->assertRedirect(route('store.order', $order->tracking_token));
        $this->assertSame('pending', $order->order_status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('Web Customer', $order->customer->name);
        $this->assertCount(1, $order->items);
        $this->assertSame($before - 2, (float) $balance->fresh()->quantity);
        $this->get(route('store.order', $order->tracking_token))->assertOk()->assertSee($order->sale_number);
    }
}
