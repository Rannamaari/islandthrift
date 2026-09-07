<?php

namespace Tests\Feature;

use App\Enums\SaleStatus;
use App\Models\CashierShift;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashierShiftEodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    #[Test]
    public function closing_a_cashier_shift_creates_an_immutable_eod_snapshot_and_a4_report(): void
    {
        $warehouse = Warehouse::factory()->create();
        $cashier = User::factory()->forWarehouse($warehouse)->create();
        $cashier->assignRole(Role::findByName('cashier'));
        $shift = CashierShift::query()->findOrFail($this->actingAs($cashier)
            ->postJson('/pos/api/shifts/open', ['opening_cash' => 20, 'notes' => 'Opening float'])
            ->assertOk()
            ->json('data.id'));
        $sale = Sale::factory()->create([
            'company_id' => $warehouse->company_id,
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id,
            'cashier_shift_id' => $shift->id,
            'status' => SaleStatus::Completed,
            'subtotal' => 100,
            'grand_total' => 100,
            'paid_total' => 100,
            'balance_due' => 0,
        ]);
        SalePayment::factory()->create([
            'company_id' => $warehouse->company_id,
            'sale_id' => $sale->id,
            'payment_method' => 'cash',
            'amount' => 100,
            'amount_tendered' => 120,
            'change_due' => 20,
        ]);

        $closeResponse = $this->actingAs($cashier)
            ->postJson("/pos/api/shifts/{$shift->id}/close", ['closing_cash' => 118, 'notes' => 'Cash count completed'])
            ->assertOk()
            ->assertJsonPath('message', 'Shift closed and EOD report generated.');
        $closed = $shift->fresh();

        $this->assertSame('closed', $closed->status);
        $this->assertSame('120.0000', $closed->expected_cash);
        $this->assertSame('118.0000', $closed->closing_cash);
        $this->assertSame('-2.0000', $closed->cash_variance);
        $this->assertSame(1, $closed->report_snapshot['sales_count']);
        $this->assertSame('100.0000', $closed->report_snapshot['cash_received']);

        $this->actingAs($cashier)
            ->get($closeResponse->json('print_url'))
            ->assertOk()
            ->assertSee('END OF DAY REPORT')
            ->assertSee($closed->shift_number);
    }

    #[Test]
    public function a_cashier_can_close_a_shift_with_no_sales_and_zero_cash(): void
    {
        $warehouse = Warehouse::factory()->create();
        $cashier = User::factory()->forWarehouse($warehouse)->create();
        $cashier->assignRole(Role::findByName('cashier'));
        $shiftId = $this->actingAs($cashier)
            ->postJson('/pos/api/shifts/open', ['opening_cash' => 0])
            ->assertOk()
            ->json('data.id');

        $this->actingAs($cashier)
            ->postJson("/pos/api/shifts/{$shiftId}/close", ['closing_cash' => 0])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $shift = CashierShift::query()->findOrFail($shiftId);
        $this->assertSame('0.0000', $shift->closing_cash);
        $this->assertSame(0, $shift->report_snapshot['sales_count']);
    }

    #[Test]
    public function an_authenticated_pos_user_can_refresh_their_csrf_token(): void
    {
        $warehouse = Warehouse::factory()->create();
        $cashier = User::factory()->forWarehouse($warehouse)->create();
        $cashier->assignRole(Role::findByName('cashier'));

        $this->actingAs($cashier)
            ->getJson('/pos/api/csrf-token')
            ->assertOk()
            ->assertHeader('Cache-Control')
            ->assertJsonStructure(['token']);

        auth()->logout();
        $this->getJson('/pos/api/csrf-token')->assertUnauthorized();
    }

    #[Test]
    public function an_admin_can_see_eod_reports_but_a_cashier_cannot_access_the_reports_section(): void
    {
        $warehouse = Warehouse::factory()->create();
        $cashier = User::factory()->forWarehouse($warehouse)->create();
        $cashier->assignRole(Role::findByName('cashier'));
        $admin = User::factory()->forWarehouse($warehouse)->create();
        $admin->assignRole(Role::findByName('admin'));

        $this->actingAs($cashier)->get('/admin/cashier-shifts')->assertForbidden();
        $this->actingAs($admin)->get('/admin/cashier-shifts')->assertOk();
    }
}
