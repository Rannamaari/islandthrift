<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class ProductCatalogDemoSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $company = Company::query()->where('name', 'Island Thrift Demo Company')->firstOrFail();

        $categories = collect([
            ['name' => 'Beverages', 'code' => 'BEV'],
            ['name' => 'Food', 'code' => 'FOOD'],
            ['name' => 'Household', 'code' => 'HOUSE'],
            ['name' => 'Personal Care', 'code' => 'CARE'],
            ['name' => 'Electronics', 'code' => 'ELEC'],
            ['name' => 'Other', 'code' => 'OTHER'],
        ])->mapWithKeys(function (array $category) use ($company) {
            $record = Category::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $category['name']],
                [
                    'code' => $category['code'],
                    'is_active' => true,
                ]
            );

            return [$category['name'] => $record];
        });

        $brands = collect([
            ['name' => 'Coca-Cola', 'code' => 'COKE'],
            ['name' => 'Pepsi', 'code' => 'PEPSI'],
            ['name' => 'Nestle', 'code' => 'NESTLE'],
            ['name' => 'Samsung', 'code' => 'SAMSNG'],
            ['name' => 'Generic', 'code' => 'GEN'],
        ])->mapWithKeys(function (array $brand) use ($company) {
            $record = Brand::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $brand['name']],
                [
                    'code' => $brand['code'],
                    'is_active' => true,
                ]
            );

            return [$brand['name'] => $record];
        });

        $units = Unit::query()->whereIn('short_name', ['btl', 'L', 'pack', 'pcs'])->get()->keyBy('short_name');

        $products = [
            [
                'sku' => 'COKE-500',
                'name' => 'Coca-Cola 500ml',
                'category' => 'Beverages',
                'brand' => 'Coca-Cola',
                'unit' => 'btl',
                'cost_price' => 8.5000,
                'selling_price' => 12.0000,
                'wholesale_price' => 10.5000,
                'tax_rate' => 8.0000,
                'minimum_stock' => 20.0000,
                'barcode' => '1234567890123',
            ],
            [
                'sku' => 'WATER-1500',
                'name' => 'Water 1.5L',
                'category' => 'Beverages',
                'brand' => 'Generic',
                'unit' => 'L',
                'cost_price' => 4.0000,
                'selling_price' => 7.5000,
                'wholesale_price' => null,
                'tax_rate' => 0,
                'minimum_stock' => 30.0000,
                'barcode' => '1234567890124',
            ],
            [
                'sku' => 'PEPSI-500',
                'name' => 'Pepsi 500ml',
                'category' => 'Beverages',
                'brand' => 'Pepsi',
                'unit' => 'btl',
                'cost_price' => 8.0000,
                'selling_price' => 11.5000,
                'wholesale_price' => 10.0000,
                'tax_rate' => 8.0000,
                'minimum_stock' => 18.0000,
                'barcode' => '1234567890125',
            ],
            [
                'sku' => 'NOODLES-001',
                'name' => 'Instant Noodles',
                'category' => 'Food',
                'brand' => 'Nestle',
                'unit' => 'pack',
                'cost_price' => 5.2500,
                'selling_price' => 8.0000,
                'wholesale_price' => 7.2500,
                'tax_rate' => 0,
                'minimum_stock' => 40.0000,
                'barcode' => '1234567890126',
            ],
            [
                'sku' => 'DISH-500',
                'name' => 'Dishwashing Liquid',
                'category' => 'Household',
                'brand' => 'Generic',
                'unit' => 'pcs',
                'cost_price' => 14.0000,
                'selling_price' => 22.5000,
                'wholesale_price' => 20.0000,
                'tax_rate' => 8.0000,
                'minimum_stock' => 12.0000,
                'barcode' => '1234567890127',
            ],
        ];

        foreach ($products as $attributes) {
            $product = Product::query()->updateOrCreate(
                ['company_id' => $company->id, 'sku' => $attributes['sku']],
                [
                    'category_id' => $categories[$attributes['category']]->id,
                    'brand_id' => $brands[$attributes['brand']]->id,
                    'unit_id' => $units[$attributes['unit']]->id,
                    'name' => $attributes['name'],
                    'cost_price' => $attributes['cost_price'],
                    'selling_price' => $attributes['selling_price'],
                    'wholesale_price' => $attributes['wholesale_price'],
                    'tax_rate' => $attributes['tax_rate'],
                    'minimum_stock' => $attributes['minimum_stock'],
                    'allow_negative_stock' => false,
                    'track_inventory' => true,
                    'is_active' => true,
                ]
            );

            ProductBarcode::query()->updateOrCreate(
                ['company_id' => $company->id, 'barcode' => $attributes['barcode']],
                [
                    'product_id' => $product->id,
                    'is_primary' => true,
                ]
            );
        }
    }
}
