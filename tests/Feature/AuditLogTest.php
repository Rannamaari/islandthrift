<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    #[Test]
    public function it_records_actor_and_before_after_values_for_price_changes(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');
        $this->actingAs($superAdmin);
        $product = Product::factory()->create([
            'company_id' => $superAdmin->company_id,
            'sku' => 'AUDIT-PRICE-1',
            'selling_price' => 100,
        ]);

        $product->update(['selling_price' => 125]);

        $log = AuditLog::query()
            ->where('entity_type', Product::class)
            ->where('entity_id', $product->id)
            ->where('event', 'updated')
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame($superAdmin->id, $log->actor_id);
        $this->assertSame($superAdmin->name, $log->actor_name);
        $this->assertSame('100.0000', $log->old_values['selling_price']);
        $this->assertSame('125', (string) $log->new_values['selling_price']);
    }

    #[Test]
    public function it_records_sales_and_discounts_and_redacts_credentials(): void
    {
        $user = User::factory()->create(['password' => 'top-secret-password']);
        $sale = Sale::factory()->create(['created_by' => $user->id, 'sale_number' => 'SAL-AUDIT-1']);
        $item = SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'company_id' => $sale->company_id,
            'discount_amount' => 15,
        ]);

        $userLog = AuditLog::query()->where('entity_type', User::class)->where('entity_id', $user->id)->where('event', 'created')->firstOrFail();
        $saleLog = AuditLog::query()->where('entity_type', Sale::class)->where('entity_id', $sale->id)->where('event', 'created')->firstOrFail();
        $itemLog = AuditLog::query()->where('entity_type', SaleItem::class)->where('entity_id', $item->id)->where('event', 'created')->firstOrFail();

        $this->assertSame('[REDACTED]', $userLog->new_values['password']);
        $this->assertSame('SAL-AUDIT-1', $saleLog->entity_label);
        $this->assertSame('15', (string) $itemLog->new_values['discount_amount']);
    }

    #[Test]
    public function only_super_admin_can_open_the_read_only_audit_log(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $this->actingAs($admin)->get('/admin/audit-logs')->assertForbidden();
        $this->actingAs($superAdmin)->get('/admin/audit-logs')->assertOk()->assertSee('Audit Log');
    }
}
