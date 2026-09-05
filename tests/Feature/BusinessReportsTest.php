<?php

namespace Tests\Feature;

use App\Enums\SaleStatus;
use App\Filament\Pages\BusinessReports;
use App\Filament\Widgets\BestSellersTable;
use App\Filament\Widgets\DailyBranchSalesChart;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BusinessReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    #[Test]
    public function an_admin_can_view_branch_scoped_business_reports(): void
    {
        $warehouse = Warehouse::factory()->create();
        $admin = User::factory()->forWarehouse($warehouse)->create();
        $admin->assignRole(Role::findByName('admin'));
        $product = Product::factory()->create([
            'company_id' => $warehouse->company_id,
            'unit_id' => Unit::factory()->create()->id,
            'name' => 'Report Cola',
            'sku' => 'REPORT-COLA',
            'cost_price' => 4,
        ]);
        $sale = Sale::factory()->create([
            'company_id' => $warehouse->company_id,
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id,
            'status' => SaleStatus::Completed,
            'sale_date' => today(),
            'subtotal' => 10,
            'grand_total' => 10,
            'paid_total' => 10,
            'balance_due' => 0,
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'company_id' => $warehouse->company_id,
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 2,
            'unit_price' => 5,
            'unit_cost' => 4,
            'line_total' => 10,
        ]);
        SalePayment::factory()->create([
            'company_id' => $warehouse->company_id,
            'sale_id' => $sale->id,
            'payment_method' => 'cash',
            'amount' => 10,
        ]);
        Sale::factory()->create([
            'company_id' => $warehouse->company_id,
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id,
            'status' => SaleStatus::Completed,
            'sale_date' => today()->subDay(),
            'subtotal' => 13,
            'grand_total' => 13,
            'paid_total' => 13,
            'balance_due' => 0,
        ]);

        $this->actingAs($admin)
            ->get('/admin/business-reports')
            ->assertOk()
            ->assertSee('Business Reports')
            ->assertSee('Report Cola')
            ->assertSee('Tender Mix')
            ->assertSee('Daily Sales')
            ->assertSee(today()->subDay()->format('M d, Y'))
            ->assertSee('13.00');

        $reportComponent = Livewire::actingAs($admin)->test(BusinessReports::class);
        $report = $reportComponent->instance()->getReportProperty();

        $this->assertSame(2, $report['summary']['transactions']);
        $this->assertSame(23.0, $report['summary']['sales_total']);

        $reportComponent
            ->call('selectDailySalesDate', today()->toDateString())
            ->assertSee('Daily Item Sales Summary')
            ->assertSee('Report Cola')
            ->assertSee('REPORT-COLA');
    }

    #[Test]
    public function a_manager_cannot_access_business_reports(): void
    {
        $warehouse = Warehouse::factory()->create();
        $manager = User::factory()->forWarehouse($warehouse)->create();
        $manager->assignRole(Role::findByName('manager'));

        $this->actingAs($manager)->get('/admin/business-reports')->assertForbidden();
    }

    #[Test]
    public function website_orders_only_count_in_best_sellers_when_completed_and_paid(): void
    {
        $warehouse = Warehouse::factory()->create();
        $admin = User::factory()->forWarehouse($warehouse)->create();
        $admin->assignRole(Role::findByName('admin'));
        $unit = Unit::factory()->create();

        $createSaleItem = function (string $productName, string $channel, ?string $orderStatus, ?string $paymentStatus) use ($warehouse, $unit): Sale {
            $product = Product::factory()->create([
                'company_id' => $warehouse->company_id,
                'unit_id' => $unit->id,
                'name' => $productName,
                'sku' => str($productName)->slug()->upper()->toString(),
            ]);
            $sale = Sale::factory()->create([
                'company_id' => $warehouse->company_id,
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'status' => SaleStatus::Completed,
                'sale_date' => today(),
                'sales_channel' => $channel,
                'order_status' => $orderStatus,
                'payment_status' => $paymentStatus,
            ]);
            SaleItem::factory()->create([
                'sale_id' => $sale->id,
                'company_id' => $warehouse->company_id,
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => 1,
                'line_total' => 100,
            ]);

            return $sale;
        };

        $posSale = $createSaleItem('Completed POS Item', 'pos', null, null);
        $paidWebsiteSale = $createSaleItem('Completed Paid Web Item', 'website', 'completed', 'paid');
        $createSaleItem('Pending Unpaid Web Item', 'website', 'pending', 'unpaid');
        $createSaleItem('Completed Unpaid Web Item', 'website', 'completed', 'unpaid');
        $createSaleItem('Pending Paid Web Item', 'website', 'pending', 'paid');

        $this->assertEqualsCanonicalizing(
            [$posSale->id, $paidWebsiteSale->id],
            Sale::query()->reportable()->pluck('id')->all(),
        );

        Livewire::actingAs($admin)
            ->test(BestSellersTable::class)
            ->assertSee('Completed POS Item')
            ->assertSee('Completed Paid Web Item')
            ->assertDontSee('Pending Unpaid Web Item')
            ->assertDontSee('Completed Unpaid Web Item')
            ->assertDontSee('Pending Paid Web Item');
    }

    #[Test]
    public function daily_sales_chart_maps_database_dates_to_the_correct_graph_point(): void
    {
        $warehouse = Warehouse::factory()->create();
        $admin = User::factory()->forWarehouse($warehouse)->create();
        $admin->assignRole(Role::findByName('admin'));
        Sale::factory()->create([
            'company_id' => $warehouse->company_id,
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id,
            'status' => SaleStatus::Completed,
            'sales_channel' => 'pos',
            'sale_date' => today(),
            'grand_total' => 125.50,
        ]);

        $widget = Livewire::actingAs($admin)->test(DailyBranchSalesChart::class)->instance();
        $method = new ReflectionMethod(DailyBranchSalesChart::class, 'getData');
        $data = $method->invoke($widget);
        $todayIndex = array_search(today()->format('M j'), $data['labels'], true);

        $this->assertNotFalse($todayIndex);
        $this->assertSame(125.50, $data['datasets'][0]['data'][$todayIndex]);
    }
}
