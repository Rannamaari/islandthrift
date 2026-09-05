<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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
            ['name' => 'Smartphones', 'code' => 'PHONES'],
            ['name' => 'Laptops', 'code' => 'LAPTOPS'],
            ['name' => 'Audio', 'code' => 'AUDIO'],
            ['name' => 'Accessories', 'code' => 'ACCESS'],
            ['name' => 'Smart Watches', 'code' => 'WATCHES'],
            ['name' => 'Gaming', 'code' => 'GAMING'],
            ['name' => 'Other', 'code' => 'OTHER'],
        ])->mapWithKeys(function (array $category) use ($company) {
            $record = Category::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $category['name']],
                [
                    'code' => $category['code'],
                    'slug' => Str::slug($category['name']),
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
            ['name' => 'Apple', 'code' => 'APPLE'],
            ['name' => 'Xiaomi', 'code' => 'XIAOMI'],
            ['name' => 'JBL', 'code' => 'JBL'],
            ['name' => 'Logitech', 'code' => 'LOGI'],
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
            [
                'sku' => 'GALAXY-A16', 'name' => 'Samsung Galaxy A16', 'category' => 'Smartphones', 'brand' => 'Samsung', 'unit' => 'pcs',
                'cost_price' => 2300, 'selling_price' => 2799, 'wholesale_price' => null, 'tax_rate' => 8, 'minimum_stock' => 2, 'barcode' => '8806095820011',
                'short_description' => 'A reliable everyday smartphone with a vivid display and long-lasting battery.', 'show_online' => true, 'is_featured' => true,
            ],
            [
                'sku' => 'AIR-M2-13', 'name' => 'MacBook Air 13-inch M2', 'category' => 'Laptops', 'brand' => 'Apple', 'unit' => 'pcs',
                'cost_price' => 12800, 'selling_price' => 14999, 'wholesale_price' => null, 'tax_rate' => 8, 'minimum_stock' => 1, 'barcode' => '194253080001',
                'short_description' => 'Thin, quiet and powerful laptop for work, study and creativity.', 'show_online' => true, 'is_featured' => true,
            ],
            [
                'sku' => 'JBL-GO4', 'name' => 'JBL Go 4 Bluetooth Speaker', 'category' => 'Audio', 'brand' => 'JBL', 'unit' => 'pcs',
                'cost_price' => 650, 'selling_price' => 799, 'wholesale_price' => null, 'tax_rate' => 8, 'minimum_stock' => 3, 'barcode' => '050036399401',
                'short_description' => 'Portable Bluetooth speaker with punchy sound in a compact design.', 'show_online' => true, 'is_featured' => true,
            ],
            [
                'sku' => 'MI-PB-20K', 'name' => 'Xiaomi 20000mAh Power Bank', 'category' => 'Accessories', 'brand' => 'Xiaomi', 'unit' => 'pcs',
                'cost_price' => 480, 'selling_price' => 599, 'wholesale_price' => null, 'tax_rate' => 8, 'minimum_stock' => 4, 'barcode' => '6934177790012',
                'short_description' => 'High-capacity portable charging for phones, earbuds and everyday devices.', 'show_online' => true, 'is_featured' => true,
            ],
            [
                'sku' => 'LOGI-G203', 'name' => 'Logitech G203 Gaming Mouse', 'category' => 'Gaming', 'brand' => 'Logitech', 'unit' => 'pcs',
                'cost_price' => 390, 'selling_price' => 499, 'wholesale_price' => null, 'tax_rate' => 8, 'minimum_stock' => 3, 'barcode' => '097855155101',
                'short_description' => 'Responsive wired gaming mouse with customizable lighting and six buttons.', 'show_online' => true, 'is_featured' => false,
            ],
            [
                'sku' => 'GALAXY-FIT3', 'name' => 'Samsung Galaxy Fit3', 'category' => 'Smart Watches', 'brand' => 'Samsung', 'unit' => 'pcs',
                'cost_price' => 750, 'selling_price' => 949, 'wholesale_price' => null, 'tax_rate' => 8, 'minimum_stock' => 2, 'barcode' => '8806095360012',
                'short_description' => 'Lightweight fitness tracker with a bright display and everyday health features.', 'show_online' => true, 'is_featured' => false,
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
                    'slug' => Str::slug($attributes['name'].'-'.$attributes['sku']),
                    'cost_price' => $attributes['cost_price'],
                    'selling_price' => $attributes['selling_price'],
                    'wholesale_price' => $attributes['wholesale_price'],
                    'tax_rate' => $attributes['tax_rate'],
                    'minimum_stock' => $attributes['minimum_stock'],
                    'allow_negative_stock' => false,
                    'track_inventory' => true,
                    'is_active' => true,
                    'short_description' => $attributes['short_description'] ?? null,
                    'show_online' => $attributes['show_online'] ?? false,
                    'is_featured' => $attributes['is_featured'] ?? false,
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
