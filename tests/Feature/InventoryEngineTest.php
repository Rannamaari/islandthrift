<?php

namespace Tests\Feature;

use App\Enums\StockCountStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InventoryException;
use App\Models\Company;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\InventoryQueryService;
use App\Services\InventoryService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InventoryEngineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function inventory_balance_belongs_to_company(): void
    {
        $balance = InventoryBalance::factory()->create();

        $this->assertInstanceOf(Company::class, $balance->company);
    }

    #[Test]
    public function inventory_balance_belongs_to_warehouse(): void
    {
        $balance = InventoryBalance::factory()->create();

        $this->assertInstanceOf(Warehouse::class, $balance->warehouse);
    }

    #[Test]
    public function inventory_balance_belongs_to_product(): void
    {
        $balance = InventoryBalance::factory()->create();

        $this->assertInstanceOf(Product::class, $balance->product);
    }

    #[Test]
    public function only_one_balance_exists_per_company_warehouse_and_product(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create([
            'company_id' => $warehouse->company_id,
            'unit_id' => Unit::factory()->create()->id,
        ]);

        InventoryBalance::factory()->create([
            'company_id' => $warehouse->company_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
        ]);

        $this->expectException(QueryException::class);

        InventoryBalance::factory()->create([
            'company_id' => $warehouse->company_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
        ]);
    }

    #[Test]
    public function opening_stock_creates_an_inventory_balance(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();

        app(InventoryService::class)->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 100, 10.5);

        $this->assertDatabaseHas('inventory_balances', [
            'company_id' => $warehouse->company_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
        ]);
    }

    #[Test]
    public function opening_stock_creates_a_stock_movement(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();

        app(InventoryService::class)->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 100, 10.5);

        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $warehouse->company_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => StockMovementType::Opening->value,
        ]);
    }

    #[Test]
    public function opening_stock_records_correct_before_and_after_quantities(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();

        $movement = app(InventoryService::class)->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 100, 10.5);

        $this->assertSame('0.0000', $movement->quantity_before);
        $this->assertSame('100.0000', $movement->quantity_after);
        $this->assertSame('100.0000', $movement->quantity);
    }

    #[Test]
    public function opening_stock_cannot_be_applied_twice(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        $service = app(InventoryService::class);

        $service->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 100, 10.5);

        $this->expectException(InventoryException::class);

        $service->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 50, 10.5);
    }

    #[Test]
    public function stock_increase_updates_balance_correctly(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        $service = app(InventoryService::class);
        $service->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 100, 10.5);

        $service->increase($warehouse->company_id, $warehouse->id, $product->id, 10);

        $this->assertSame('110.0000', $service->getBalance($warehouse->company_id, $warehouse->id, $product->id));
    }

    #[Test]
    public function stock_decrease_updates_balance_correctly(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        $service = app(InventoryService::class);
        $service->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 100, 10.5);

        $service->decrease($warehouse->company_id, $warehouse->id, $product->id, 15);

        $this->assertSame('85.0000', $service->getBalance($warehouse->company_id, $warehouse->id, $product->id));
    }

    #[Test]
    public function stock_decrease_creates_correct_movement(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        $service = app(InventoryService::class);
        $service->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 100, 10.5);

        $movement = $service->decrease($warehouse->company_id, $warehouse->id, $product->id, 15);

        $this->assertSame(StockMovementType::AdjustmentOut, $movement->type);
        $this->assertSame('-15.0000', $movement->quantity);
        $this->assertSame('100.0000', $movement->quantity_before);
        $this->assertSame('85.0000', $movement->quantity_after);
    }

    #[Test]
    public function damage_reduces_stock(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        $service = app(InventoryService::class);
        $service->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 50, 10.5);

        $movement = $service->recordDamage($warehouse->company_id, $warehouse->id, $product->id, 4);

        $this->assertSame(StockMovementType::Damage, $movement->type);
        $this->assertSame('46.0000', $service->getBalance($warehouse->company_id, $warehouse->id, $product->id));
    }

    #[Test]
    public function loss_reduces_stock(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        $service = app(InventoryService::class);
        $service->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 50, 10.5);

        $movement = $service->recordLoss($warehouse->company_id, $warehouse->id, $product->id, 2);

        $this->assertSame(StockMovementType::Loss, $movement->type);
        $this->assertSame('48.0000', $service->getBalance($warehouse->company_id, $warehouse->id, $product->id));
    }

    #[Test]
    public function manual_adjustment_increases_stock(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        $service = app(InventoryService::class);
        $service->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 10, 10.5);

        $movement = $service->adjust($warehouse->company_id, $warehouse->id, $product->id, 15, null, 'Adjustment');

        $this->assertSame(StockMovementType::AdjustmentIn, $movement?->type);
        $this->assertSame('15.0000', $service->getBalance($warehouse->company_id, $warehouse->id, $product->id));
    }

    #[Test]
    public function manual_adjustment_decreases_stock(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        $service = app(InventoryService::class);
        $service->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 10, 10.5);

        $movement = $service->adjust($warehouse->company_id, $warehouse->id, $product->id, 7, null, 'Adjustment');

        $this->assertSame(StockMovementType::AdjustmentOut, $movement?->type);
        $this->assertSame('7.0000', $service->getBalance($warehouse->company_id, $warehouse->id, $product->id));
    }

    #[Test]
    public function negative_stock_is_rejected_when_product_disallows_it(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct(['allow_negative_stock' => false]);

        $this->expectException(InventoryException::class);

        app(InventoryService::class)->decrease($warehouse->company_id, $warehouse->id, $product->id, 1);
    }

    #[Test]
    public function negative_stock_is_allowed_when_configured(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct(['allow_negative_stock' => true]);

        app(InventoryService::class)->decrease($warehouse->company_id, $warehouse->id, $product->id, 5);

        $this->assertSame('-5.0000', app(InventoryService::class)->getBalance($warehouse->company_id, $warehouse->id, $product->id));
    }

    #[Test]
    public function cross_company_warehouse_product_combination_is_rejected(): void
    {
        $warehouse = Warehouse::factory()->create();
        $otherCompany = Company::factory()->create();
        $product = Product::factory()->create([
            'company_id' => $otherCompany->id,
            'unit_id' => Unit::factory()->create()->id,
        ]);

        $this->expectException(InventoryException::class);

        app(InventoryService::class)->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 10, 10);
    }

    #[Test]
    public function stock_movement_belongs_to_correct_warehouse_product_and_company(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();

        $movement = app(InventoryService::class)->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 10, 10);

        $this->assertSame($warehouse->company_id, $movement->company_id);
        $this->assertSame($warehouse->id, $movement->warehouse_id);
        $this->assertSame($product->id, $movement->product_id);
    }

    #[Test]
    public function non_inventory_products_reject_stock_operations(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct(['track_inventory' => false]);

        $this->expectException(InventoryException::class);

        app(InventoryService::class)->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 10, 10);
    }

    #[Test]
    public function stock_count_can_be_created(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();

        $count = app(InventoryService::class)->createStockCount($warehouse->company_id, $warehouse->id, [$product->id]);

        $this->assertInstanceOf(StockCount::class, $count);
        $this->assertSame(StockCountStatus::InProgress, $count->status);
    }

    #[Test]
    public function stock_count_stores_system_quantity(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        $service = app(InventoryService::class);
        $service->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 20, 10);

        $count = $service->createStockCount($warehouse->company_id, $warehouse->id, [$product->id]);

        $this->assertSame('20.0000', $count->items->first()?->system_quantity);
    }

    #[Test]
    public function stock_count_item_stores_counted_quantity(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        $service = app(InventoryService::class);
        $count = $service->createStockCount($warehouse->company_id, $warehouse->id, [$product->id]);

        $item = $service->setCountedQuantity($count->items->first()->id, 18);

        $this->assertSame('18.0000', $item->counted_quantity);
    }

    #[Test]
    public function stock_count_calculates_difference(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        $service = app(InventoryService::class);
        $service->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 20, 10);
        $count = $service->createStockCount($warehouse->company_id, $warehouse->id, [$product->id]);

        $item = $service->setCountedQuantity($count->items->first()->id, 18);

        $this->assertSame('-2.0000', $item->difference);
    }

    #[Test]
    public function completing_stock_count_updates_inventory(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        $service = app(InventoryService::class);
        $service->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 20, 10);
        $count = $service->createStockCount($warehouse->company_id, $warehouse->id, [$product->id]);
        $service->setCountedQuantity($count->items->first()->id, 18);

        $service->completeStockCount($count->id);

        $this->assertSame('18.0000', $service->getBalance($warehouse->company_id, $warehouse->id, $product->id));
    }

    #[Test]
    public function completing_stock_count_creates_stock_movement(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        $service = app(InventoryService::class);
        $service->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 20, 10);
        $count = $service->createStockCount($warehouse->company_id, $warehouse->id, [$product->id]);
        $service->setCountedQuantity($count->items->first()->id, 18);

        $service->completeStockCount($count->id);

        $this->assertDatabaseHas('stock_movements', [
            'type' => StockMovementType::StockCount->value,
            'product_id' => $product->id,
        ]);
    }

    #[Test]
    public function stock_count_completion_uses_current_balance_not_stale_snapshot_when_determining_adjustment(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        $service = app(InventoryService::class);
        $service->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 20, 10);
        $count = $service->createStockCount($warehouse->company_id, $warehouse->id, [$product->id]);
        $service->setCountedQuantity($count->items->first()->id, 18);
        $service->increase($warehouse->company_id, $warehouse->id, $product->id, 5);

        $service->completeStockCount($count->id);

        $movement = StockMovement::query()->where('type', StockMovementType::StockCount)->latest('created_at')->firstOrFail();

        $this->assertSame('25.0000', $movement->quantity_before);
        $this->assertSame('-7.0000', $movement->quantity);
        $this->assertSame('18.0000', $movement->quantity_after);
    }

    #[Test]
    public function completed_stock_count_cannot_be_completed_twice(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        $service = app(InventoryService::class);
        $count = $service->createStockCount($warehouse->company_id, $warehouse->id, [$product->id]);
        $service->setCountedQuantity($count->items->first()->id, 0);
        $service->completeStockCount($count->id);

        $this->expectException(InventoryException::class);

        $service->completeStockCount($count->id);
    }

    #[Test]
    public function cancelled_stock_count_does_not_alter_inventory(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        $service = app(InventoryService::class);
        $service->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 20, 10);
        $count = $service->createStockCount($warehouse->company_id, $warehouse->id, [$product->id]);
        $service->setCountedQuantity($count->items->first()->id, 10);
        $service->cancelStockCount($count->id);

        $this->assertSame('20.0000', $service->getBalance($warehouse->company_id, $warehouse->id, $product->id));
        $this->assertDatabaseMissing('stock_movements', ['type' => StockMovementType::StockCount->value]);
    }

    #[Test]
    public function low_stock_query_returns_products_below_threshold(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct(['minimum_stock' => 10]);
        app(InventoryService::class)->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 5, 10);

        $results = app(InventoryQueryService::class)->lowStockByWarehouse($warehouse->company_id, $warehouse->id)->get();

        $this->assertTrue($results->contains('id', $product->id));
    }

    #[Test]
    public function low_stock_query_excludes_non_inventory_products(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct([
            'track_inventory' => false,
            'minimum_stock' => 10,
        ]);

        $results = app(InventoryQueryService::class)->lowStockByWarehouse($warehouse->company_id, $warehouse->id)->get();

        $this->assertFalse($results->contains('id', $product->id));
    }

    #[Test]
    public function low_stock_query_respects_warehouse(): void
    {
        $warehouseA = Warehouse::factory()->create();
        $warehouseB = Warehouse::factory()->create(['company_id' => $warehouseA->company_id, 'branch_id' => $warehouseA->branch_id]);
        $product = Product::factory()->create([
            'company_id' => $warehouseA->company_id,
            'unit_id' => Unit::factory()->create()->id,
            'minimum_stock' => 10,
        ]);
        $service = app(InventoryService::class);
        $service->setOpeningStock($warehouseA->company_id, $warehouseA->id, $product->id, 5, 10);
        $service->setOpeningStock($warehouseB->company_id, $warehouseB->id, $product->id, 20, 10);

        $results = app(InventoryQueryService::class)->lowStockByWarehouse($warehouseA->company_id, $warehouseA->id)->get();

        $this->assertTrue($results->contains('id', $product->id));
        $this->assertFalse(app(InventoryQueryService::class)->lowStockByWarehouse($warehouseB->company_id, $warehouseB->id)->get()->contains('id', $product->id));
    }

    #[Test]
    public function inventory_valuation_returns_expected_basic_value(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct(['cost_price' => 12.5]);
        app(InventoryService::class)->setOpeningStock($warehouse->company_id, $warehouse->id, $product->id, 20, 12.5);

        $this->assertSame('250.0000', app(InventoryQueryService::class)->inventoryValuation($warehouse->company_id, $warehouse->id));
    }

    #[Test]
    public function movement_history_can_filter_by_product(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        [$__, $otherProduct] = $this->warehouseAndProduct(['company_id' => $warehouse->company_id]);
        $service = app(InventoryService::class);
        $service->increase($warehouse->company_id, $warehouse->id, $product->id, 3);
        $service->increase($warehouse->company_id, $warehouse->id, $otherProduct->id, 4);

        $results = app(InventoryQueryService::class)
            ->movementHistory($warehouse->company_id, ['product_id' => $product->id])
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame($product->id, $results->first()->product_id);
    }

    #[Test]
    public function movement_history_can_filter_by_warehouse(): void
    {
        $warehouseA = Warehouse::factory()->create();
        $warehouseB = Warehouse::factory()->create(['company_id' => $warehouseA->company_id, 'branch_id' => $warehouseA->branch_id]);
        $product = Product::factory()->create([
            'company_id' => $warehouseA->company_id,
            'unit_id' => Unit::factory()->create()->id,
        ]);
        $service = app(InventoryService::class);
        $service->increase($warehouseA->company_id, $warehouseA->id, $product->id, 3);
        $service->increase($warehouseB->company_id, $warehouseB->id, $product->id, 4);

        $results = app(InventoryQueryService::class)
            ->movementHistory($warehouseA->company_id, ['warehouse_id' => $warehouseA->id])
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame($warehouseA->id, $results->first()->warehouse_id);
    }

    #[Test]
    public function movement_history_can_filter_by_date_range(): void
    {
        [$warehouse, $product] = $this->warehouseAndProduct();
        StockMovement::factory()->create([
            'company_id' => $warehouse->company_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'occurred_at' => now()->subDays(3),
        ]);
        StockMovement::factory()->create([
            'company_id' => $warehouse->company_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'occurred_at' => now(),
        ]);

        $results = app(InventoryQueryService::class)
            ->movementHistory($warehouse->company_id, [
                'from' => now()->subDay(),
                'to' => now()->addDay(),
            ])
            ->get();

        $this->assertCount(1, $results);
    }

    #[Test]
    public function inventory_permissions_are_seeded(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertDatabaseHas('permissions', ['name' => 'inventory.count']);
        $this->assertDatabaseHas('permissions', ['name' => 'inventory.history']);
    }

    #[Test]
    public function seeded_inventory_data_is_created_with_opening_movements(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::query()->where('name', 'Island Thrift Demo Company')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $company->id)->where('code', 'MAIN-WH')->firstOrFail();
        $product = Product::query()->where('company_id', $company->id)->where('sku', 'COKE-500')->firstOrFail();

        $this->assertDatabaseHas('inventory_balances', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => StockMovementType::Opening->value,
        ]);
    }

    private function warehouseAndProduct(array $productOverrides = []): array
    {
        $warehouse = Warehouse::factory()->create([
            'company_id' => $productOverrides['company_id'] ?? null,
        ]);

        $product = Product::factory()->create(array_merge([
            'company_id' => $productOverrides['company_id'] ?? $warehouse->company_id,
            'unit_id' => Unit::factory()->create()->id,
            'minimum_stock' => 0,
        ], $productOverrides));

        return [$warehouse, $product];
    }
}
