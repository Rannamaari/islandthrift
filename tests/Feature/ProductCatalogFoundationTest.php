<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductBranchPrice;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\CsvDataImportService;
use App\Services\InventoryService;
use App\Services\ProductImportService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LargeProductCatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UnitsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductCatalogFoundationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function company_has_many_products(): void
    {
        $company = Company::factory()->create();
        Product::factory()->count(2)->create(['company_id' => $company->id, 'unit_id' => Unit::factory()->create()->id]);

        $this->assertCount(2, $company->fresh()->products);
    }

    #[Test]
    public function category_belongs_to_company(): void
    {
        $category = Category::factory()->create();

        $this->assertInstanceOf(Company::class, $category->company);
    }

    #[Test]
    public function category_may_have_child_categories(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();

        $this->assertTrue($parent->children->contains($child));
    }

    #[Test]
    public function category_cannot_use_a_parent_belonging_to_another_company(): void
    {
        $parent = Category::factory()->create();
        $category = Category::factory()->make([
            'company_id' => Company::factory()->create()->id,
            'parent_id' => $parent->id,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $category->save();
    }

    #[Test]
    public function brand_belongs_to_company(): void
    {
        $brand = Brand::factory()->create();

        $this->assertInstanceOf(Company::class, $brand->company);
    }

    #[Test]
    public function product_belongs_to_company(): void
    {
        $product = Product::factory()->create(['unit_id' => Unit::factory()->create()->id]);

        $this->assertInstanceOf(Company::class, $product->company);
    }

    #[Test]
    public function product_belongs_to_category(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'company_id' => $category->company_id,
            'category_id' => $category->id,
            'unit_id' => Unit::factory()->create()->id,
        ]);

        $this->assertInstanceOf(Category::class, $product->category);
    }

    #[Test]
    public function product_belongs_to_brand(): void
    {
        $brand = Brand::factory()->create();
        $product = Product::factory()->create([
            'company_id' => $brand->company_id,
            'brand_id' => $brand->id,
            'unit_id' => Unit::factory()->create()->id,
        ]);

        $this->assertInstanceOf(Brand::class, $product->brand);
    }

    #[Test]
    public function product_belongs_to_unit(): void
    {
        $unit = Unit::factory()->create();
        $product = Product::factory()->create(['unit_id' => $unit->id]);

        $this->assertInstanceOf(Unit::class, $product->unit);
    }

    #[Test]
    public function product_has_multiple_barcodes(): void
    {
        $product = Product::factory()->create(['unit_id' => Unit::factory()->create()->id]);
        ProductBarcode::factory()->count(2)->create([
            'product_id' => $product->id,
            'company_id' => $product->company_id,
        ]);

        $this->assertCount(2, $product->fresh()->barcodes);
    }

    #[Test]
    public function deleting_an_unused_product_removes_its_branch_prices(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $product = Product::factory()->create(['company_id' => $company->id, 'unit_id' => Unit::factory()->create()->id]);
        $branchPrice = ProductBranchPrice::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'currency' => $branch->currency,
            'cost_price' => 5,
            'selling_price' => 10,
        ]);

        $product->delete();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_branch_prices', ['id' => $branchPrice->id]);
    }

    #[Test]
    public function sku_is_unique_within_a_company(): void
    {
        $company = Company::factory()->create();
        $unit = Unit::factory()->create();

        Product::factory()->create(['company_id' => $company->id, 'unit_id' => $unit->id, 'sku' => 'SKU-001']);

        $this->expectException(QueryException::class);

        Product::factory()->create(['company_id' => $company->id, 'unit_id' => $unit->id, 'sku' => 'SKU-001']);
    }

    #[Test]
    public function same_sku_may_exist_in_different_companies(): void
    {
        $unit = Unit::factory()->create();

        Product::factory()->create(['unit_id' => $unit->id, 'sku' => 'SKU-001']);
        Product::factory()->create(['unit_id' => $unit->id, 'sku' => 'SKU-001']);

        $this->assertSame(2, Product::query()->where('sku', 'SKU-001')->count());
    }

    #[Test]
    public function barcode_is_unique_within_a_company(): void
    {
        $product = Product::factory()->create(['unit_id' => Unit::factory()->create()->id]);
        ProductBarcode::factory()->create([
            'product_id' => $product->id,
            'company_id' => $product->company_id,
            'barcode' => '123456',
        ]);

        $this->expectException(QueryException::class);

        ProductBarcode::factory()->create([
            'product_id' => $product->id,
            'company_id' => $product->company_id,
            'barcode' => '123456',
        ]);
    }

    #[Test]
    public function same_barcode_may_exist_in_different_companies(): void
    {
        $productA = Product::factory()->create(['unit_id' => Unit::factory()->create()->id]);
        $productB = Product::factory()->create(['unit_id' => Unit::factory()->create()->id]);

        ProductBarcode::factory()->create([
            'product_id' => $productA->id,
            'company_id' => $productA->company_id,
            'barcode' => '123456',
        ]);

        ProductBarcode::factory()->create([
            'product_id' => $productB->id,
            'company_id' => $productB->company_id,
            'barcode' => '123456',
        ]);

        $this->assertSame(2, ProductBarcode::query()->where('barcode', '123456')->count());
    }

    #[Test]
    public function barcode_cannot_belong_to_a_product_from_a_different_company(): void
    {
        $product = Product::factory()->create(['unit_id' => Unit::factory()->create()->id]);
        $otherCompany = Company::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        ProductBarcode::factory()->create([
            'product_id' => $product->id,
            'company_id' => $otherCompany->id,
            'barcode' => 'DIFF-COMPANY',
        ]);
    }

    #[Test]
    public function only_one_primary_barcode_exists_for_a_product(): void
    {
        $product = Product::factory()->create(['unit_id' => Unit::factory()->create()->id]);

        ProductBarcode::factory()->primary()->create([
            'product_id' => $product->id,
            'company_id' => $product->company_id,
            'barcode' => 'PRIMARY-1',
        ]);

        ProductBarcode::factory()->primary()->create([
            'product_id' => $product->id,
            'company_id' => $product->company_id,
            'barcode' => 'PRIMARY-2',
        ]);

        $this->assertSame(1, ProductBarcode::query()->where('product_id', $product->id)->where('is_primary', true)->count());
    }

    #[Test]
    public function setting_a_new_primary_barcode_updates_the_old_one(): void
    {
        $product = Product::factory()->create(['unit_id' => Unit::factory()->create()->id]);

        $oldPrimary = ProductBarcode::factory()->primary()->create([
            'product_id' => $product->id,
            'company_id' => $product->company_id,
            'barcode' => 'PRIMARY-1',
        ]);

        $newPrimary = ProductBarcode::factory()->primary()->create([
            'product_id' => $product->id,
            'company_id' => $product->company_id,
            'barcode' => 'PRIMARY-2',
        ]);

        $this->assertFalse($oldPrimary->fresh()->is_primary);
        $this->assertTrue($newPrimary->fresh()->is_primary);
    }

    #[Test]
    public function exact_barcode_search_returns_correct_product(): void
    {
        $product = Product::factory()->create(['unit_id' => Unit::factory()->create()->id, 'name' => 'Barcode Product']);
        ProductBarcode::factory()->primary()->create([
            'product_id' => $product->id,
            'company_id' => $product->company_id,
            'barcode' => 'BAR-001',
        ]);

        $result = Product::findByBarcode($product->company_id, 'BAR-001');

        $this->assertSame($product->id, $result?->id);
    }

    #[Test]
    public function exact_sku_search_returns_correct_product(): void
    {
        $product = Product::factory()->create(['unit_id' => Unit::factory()->create()->id, 'sku' => 'SKU-FOUND']);

        $result = Product::findBySku($product->company_id, 'SKU-FOUND');

        $this->assertSame($product->id, $result?->id);
    }

    #[Test]
    public function product_name_search_returns_relevant_results(): void
    {
        $product = Product::factory()->create(['unit_id' => Unit::factory()->create()->id, 'name' => 'Coca Cola Zero']);

        $results = Product::searchForCompany($product->company_id, 'cOcA')->get();

        $this->assertTrue($results->contains('id', $product->id));
    }

    #[Test]
    public function barcode_lookup_does_not_fall_back_to_name_search(): void
    {
        $product = Product::factory()->create([
            'unit_id' => Unit::factory()->create()->id,
            'name' => 'SCAN-ONLY',
        ]);

        $this->assertNull(Product::findByBarcode($product->company_id, 'SCAN-ONLY'));
    }

    #[Test]
    public function sku_lookup_does_not_fall_back_to_name_search(): void
    {
        $product = Product::factory()->create([
            'unit_id' => Unit::factory()->create()->id,
            'name' => 'SKU-NAME-ONLY',
        ]);

        $this->assertNull(Product::findBySku($product->company_id, 'SKU-NAME-ONLY'));
    }

    #[Test]
    public function exact_barcode_lookup_is_company_scoped(): void
    {
        $product = Product::factory()->create(['unit_id' => Unit::factory()->create()->id]);
        ProductBarcode::factory()->primary()->create([
            'product_id' => $product->id,
            'company_id' => $product->company_id,
            'barcode' => 'COMPANY-BARCODE',
        ]);

        $otherCompany = Company::factory()->create();

        $this->assertNull(Product::findByBarcode($otherCompany->id, 'COMPANY-BARCODE'));
    }

    #[Test]
    public function exact_sku_lookup_is_company_scoped(): void
    {
        $product = Product::factory()->create([
            'unit_id' => Unit::factory()->create()->id,
            'sku' => 'COMPANY-SKU',
        ]);

        $otherCompany = Company::factory()->create();

        $this->assertNull(Product::findBySku($otherCompany->id, 'COMPANY-SKU'));
    }

    #[Test]
    public function inactive_products_are_excluded_from_exact_barcode_lookup(): void
    {
        $product = Product::factory()->inactive()->create(['unit_id' => Unit::factory()->create()->id]);
        ProductBarcode::factory()->primary()->create([
            'product_id' => $product->id,
            'company_id' => $product->company_id,
            'barcode' => 'INACTIVE-BARCODE',
        ]);

        $this->assertNull(Product::findByBarcode($product->company_id, 'INACTIVE-BARCODE'));
    }

    #[Test]
    public function inactive_products_are_excluded_from_exact_sku_lookup(): void
    {
        $product = Product::factory()->inactive()->create([
            'unit_id' => Unit::factory()->create()->id,
            'sku' => 'INACTIVE-SKU',
        ]);

        $this->assertNull(Product::findBySku($product->company_id, 'INACTIVE-SKU'));
    }

    #[Test]
    public function inactive_products_are_excluded_from_normal_search(): void
    {
        $product = Product::factory()->inactive()->create(['unit_id' => Unit::factory()->create()->id, 'name' => 'Inactive Search']);

        $results = Product::searchForCompany($product->company_id, 'Inactive')->get();

        $this->assertFalse($results->contains('id', $product->id));
    }

    #[Test]
    public function category_filter_works(): void
    {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create(['company_id' => $category->company_id]);
        $unit = Unit::factory()->create();

        $matching = Product::factory()->create([
            'company_id' => $category->company_id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Filter Match',
        ]);

        Product::factory()->create([
            'company_id' => $category->company_id,
            'category_id' => $otherCategory->id,
            'unit_id' => $unit->id,
            'name' => 'Filter Miss',
        ]);

        $results = Product::searchForCompany($category->company_id, 'Filter', ['category_id' => $category->id])->get();

        $this->assertTrue($results->contains('id', $matching->id));
        $this->assertCount(1, $results);
    }

    #[Test]
    public function brand_filter_works(): void
    {
        $brand = Brand::factory()->create();
        $otherBrand = Brand::factory()->create(['company_id' => $brand->company_id]);
        $unit = Unit::factory()->create();

        $matching = Product::factory()->create([
            'company_id' => $brand->company_id,
            'brand_id' => $brand->id,
            'unit_id' => $unit->id,
            'name' => 'Brand Match',
        ]);

        Product::factory()->create([
            'company_id' => $brand->company_id,
            'brand_id' => $otherBrand->id,
            'unit_id' => $unit->id,
            'name' => 'Brand Miss',
        ]);

        $results = Product::searchForCompany($brand->company_id, 'Brand', ['brand_id' => $brand->id])->get();

        $this->assertTrue($results->contains('id', $matching->id));
        $this->assertCount(1, $results);
    }

    #[Test]
    public function product_prices_reject_invalid_negative_values_where_application_validation_applies(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Product::factory()->create([
            'unit_id' => Unit::factory()->create()->id,
            'selling_price' => -1,
        ]);
    }

    #[Test]
    public function seeders_create_expected_default_categories(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('categories', ['name' => 'Beverages']);
        $this->assertDatabaseHas('categories', ['name' => 'Electronics']);
    }

    #[Test]
    public function seeders_create_default_units(): void
    {
        $this->seed(UnitsSeeder::class);

        $this->assertDatabaseHas('units', ['short_name' => 'pcs']);
        $this->assertDatabaseHas('units', ['short_name' => 'kg']);
    }

    #[Test]
    public function product_permissions_are_seeded_correctly(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertDatabaseHas('permissions', ['name' => 'products.import']);
        $this->assertTrue(Permission::query()->where('name', 'categories.manage')->exists());
    }

    #[Test]
    public function csv_import_accepts_valid_products(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::query()->where('name', 'Island Thrift Demo Company')->firstOrFail();
        $csv = $this->makeCsv([
            ['sku', 'name', 'barcode', 'category', 'brand', 'unit', 'cost_price', 'selling_price', 'wholesale_price', 'tax_rate', 'minimum_stock'],
            ['CSV-001', 'CSV Cola', 'CSVBAR001', 'Beverages', 'Coca-Cola', 'btl', '5.5', '9.5', '8.0', '8', '10'],
        ]);

        $result = app(ProductImportService::class)->import($company->id, $csv);

        $this->assertSame(1, $result['created']);
        $this->assertEmpty($result['errors']);
        $this->assertDatabaseHas('products', ['company_id' => $company->id, 'sku' => 'CSV-001']);
    }

    #[Test]
    public function csv_import_rejects_duplicate_sku(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::query()->where('name', 'Island Thrift Demo Company')->firstOrFail();
        $csv = $this->makeCsv([
            ['sku', 'name', 'barcode', 'category', 'brand', 'unit', 'cost_price', 'selling_price'],
            ['COKE-500', 'Duplicate SKU', 'CSVBAR002', 'Beverages', 'Coca-Cola', 'btl', '5.5', '9.5'],
        ]);

        $result = app(ProductImportService::class)->import($company->id, $csv);

        $this->assertSame(0, $result['created']);
        $this->assertNotEmpty($result['errors']);
    }

    #[Test]
    public function csv_import_rejects_duplicate_barcode(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::query()->where('name', 'Island Thrift Demo Company')->firstOrFail();
        $csv = $this->makeCsv([
            ['sku', 'name', 'barcode', 'category', 'brand', 'unit', 'cost_price', 'selling_price'],
            ['CSV-002', 'Duplicate Barcode', '1234567890123', 'Beverages', 'Coca-Cola', 'btl', '5.5', '9.5'],
        ]);

        $result = app(ProductImportService::class)->import($company->id, $csv);

        $this->assertSame(0, $result['created']);
        $this->assertNotEmpty($result['errors']);
    }

    #[Test]
    public function csv_data_import_previews_duplicates_and_creates_opening_stock(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::query()->where('name', 'Island Thrift Demo Company')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $company->id)->firstOrFail();
        $csv = $this->makeCsv([
            ['sku', 'name', 'barcode', 'category', 'brand', 'unit', 'cost_price', 'selling_price', 'tax_rate', 'minimum_stock', 'initial_quantity', 'opening_unit_cost'],
            ['CSV-SAFE-001', 'Safe Import Product', 'SAFE-IMPORT-001', 'Imported', 'Imported Brand', 'btl', '5.5', '9.5', '0', '1', '12', '5.5'],
        ]);

        $importer = app(CsvDataImportService::class);
        $preview = $importer->preview($company->id, 'products', $csv, $warehouse->id);
        $result = $importer->import($company->id, 'products', $csv, $warehouse->id);

        $this->assertSame(1, $preview['valid']);
        $this->assertSame(1, $preview['total']);
        $this->assertSame('Safe Import Product', $preview['rows'][0]['data']['name']);
        $this->assertSame('CSV-SAFE-001', $preview['rows'][0]['data']['sku']);
        $this->assertSame(1, $result['created']);
        $product = Product::query()->where('company_id', $company->id)->where('sku', 'CSV-SAFE-001')->firstOrFail();
        $this->assertSame('12.0000', app(InventoryService::class)->getBalance($company->id, $warehouse->id, $product->id));
        $this->assertSame(1, $importer->preview($company->id, 'products', $csv, $warehouse->id)['duplicates']);
    }

    #[Test]
    public function csv_data_import_allows_optional_product_columns_and_zero_placeholder_barcodes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::query()->where('name', 'Island Thrift Demo Company')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $company->id)->firstOrFail();
        $csv = $this->makeCsv([
            ['sku', 'name', 'barcode', 'category', 'brand', 'selling_price', 'initial_quantity'],
            ['266', 'Anke Bracelet gold platted', '0', 'system', 'bracelet', '25', '4'],
            ['267', 'Glass Shark', '', 'system', 'bracelet', '10', '22'],
        ]);

        $importer = app(CsvDataImportService::class);
        $preview = $importer->preview($company->id, 'products', $csv, $warehouse->id);
        $result = $importer->import($company->id, 'products', $csv, $warehouse->id);

        $this->assertSame(2, $preview['valid']);
        $this->assertSame(2, $result['created']);
        $product = Product::query()->where('company_id', $company->id)->where('sku', '266')->firstOrFail();
        $this->assertSame('0.0000', $product->cost_price);
        $this->assertSame('25.0000', $product->selling_price);
        $this->assertNull($product->primaryBarcode);
        $this->assertSame('4.0000', app(InventoryService::class)->getBalance($company->id, $warehouse->id, $product->id));
    }

    #[Test]
    public function large_product_data_can_be_paginated_without_loading_the_full_catalog(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(LargeProductCatalogSeeder::class);

        $company = Company::query()->where('name', 'Island Thrift Demo Company')->firstOrFail();

        $page = Product::query()
            ->with(['category', 'brand', 'unit', 'primaryBarcode'])
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->paginate(50);

        $this->assertCount(50, $page->items());
        $this->assertGreaterThan(50, $page->total());
    }

    private function makeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'island-thrift-csv-');
        $handle = fopen($path, 'wb');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }
}
