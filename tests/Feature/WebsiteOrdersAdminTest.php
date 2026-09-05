<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebsiteOrdersAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function website_orders_and_pos_sales_have_separate_admin_lists(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@islandthrift.local')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $admin->company_id)->firstOrFail();
        $customer = Customer::factory()->create([
            'company_id' => $admin->company_id,
            'name' => 'Website Customer',
            'phone' => '7771234',
        ]);

        $websiteOrder = Sale::factory()->create([
            'company_id' => $admin->company_id,
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'sale_number' => 'WEB-ORDER-100',
            'sales_channel' => 'website',
            'order_status' => 'pending',
            'payment_status' => 'unpaid',
            'website_payment_method' => 'cash',
            'delivery_method' => 'local_delivery',
            'delivery_address' => 'Harbour area, Himmafushi',
        ]);
        Sale::factory()->create([
            'company_id' => $admin->company_id,
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id,
            'sale_number' => 'POS-ORDER-100',
            'sales_channel' => 'pos',
        ]);

        $this->actingAs($admin)
            ->get('/admin/website-orders')
            ->assertOk()
            ->assertSee('WEB-ORDER-100')
            ->assertSee('Website Customer')
            ->assertSee('Delivery')
            ->assertDontSee('POS-ORDER-100');

        $this->actingAs($admin)
            ->get('/admin/sales')
            ->assertOk()
            ->assertSee('POS-ORDER-100')
            ->assertDontSee('WEB-ORDER-100');

        $this->actingAs($admin)
            ->get("/admin/website-orders/{$websiteOrder->id}")
            ->assertOk()
            ->assertSee('Harbour area, Himmafushi')
            ->assertSee('7771234')
            ->assertSee('Delivery');
    }
}
