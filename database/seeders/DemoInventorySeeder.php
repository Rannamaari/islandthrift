<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;

class DemoInventorySeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(InventoryService $inventoryService): void
    {
        $company = Company::query()->where('name', 'Island Thrift Demo Company')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('company_id', $company->id)
            ->where('code', 'MAIN-WH')
            ->firstOrFail();

        $openingStock = [
            'COKE-500' => ['quantity' => 100, 'unit_cost' => 8.5000],
            'WATER-1500' => ['quantity' => 80, 'unit_cost' => 4.0000],
            'PEPSI-500' => ['quantity' => 75, 'unit_cost' => 8.0000],
            'NOODLES-001' => ['quantity' => 60, 'unit_cost' => 5.2500],
            'DISH-500' => ['quantity' => 30, 'unit_cost' => 14.0000],
        ];

        foreach ($openingStock as $sku => $data) {
            $product = Product::query()
                ->where('company_id', $company->id)
                ->where('sku', $sku)
                ->first();

            if (! $product) {
                continue;
            }

            $hasOpening = $product->stockMovements()
                ->where('company_id', $company->id)
                ->where('warehouse_id', $warehouse->id)
                ->where('type', StockMovementType::Opening)
                ->exists();

            if ($hasOpening) {
                continue;
            }

            $inventoryService->setOpeningStock(
                $company->id,
                $warehouse->id,
                $product->id,
                $data['quantity'],
                $data['unit_cost'],
                null,
                now(),
                'Seeded opening stock'
            );
        }
    }
}
