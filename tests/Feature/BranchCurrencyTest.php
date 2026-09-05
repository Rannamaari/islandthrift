<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductBranchPrice;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_use_their_branch_currency_and_price(): void
    {
        $company = Company::factory()->create(['currency' => 'MVR']);
        $mvrBranch = Branch::factory()->create(['company_id' => $company->id, 'currency' => 'MVR']);
        $usdBranch = Branch::factory()->create(['company_id' => $company->id, 'currency' => 'USD']);
        $mvrWarehouse = Warehouse::factory()->create(['company_id' => $company->id, 'branch_id' => $mvrBranch->id]);
        $usdWarehouse = Warehouse::factory()->create(['company_id' => $company->id, 'branch_id' => $usdBranch->id]);
        $product = Product::factory()->create(['company_id' => $company->id, 'unit_id' => Unit::factory()->create()->id, 'selling_price' => 20, 'tax_rate' => 0, 'track_inventory' => false]);

        ProductBranchPrice::query()->create([
            'company_id' => $company->id,
            'branch_id' => $usdBranch->id,
            'product_id' => $product->id,
            'currency' => 'USD',
            'cost_price' => 1,
            'selling_price' => 3,
        ]);

        $mvrSale = app(SalesService::class)->createSale($company->id, $mvrBranch->id, $mvrWarehouse->id, [['product_id' => $product->id, 'quantity' => 1]], [['amount' => 20]]);
        $usdSale = app(SalesService::class)->createSale($company->id, $usdBranch->id, $usdWarehouse->id, [['product_id' => $product->id, 'quantity' => 1]], [['amount' => 3]]);

        $this->assertSame('MVR', $mvrSale->currency);
        $this->assertSame('20.0000', $mvrSale->grand_total);
        $this->assertSame('USD', $usdSale->currency);
        $this->assertSame('3.0000', $usdSale->grand_total);
        $this->assertSame('USD', $usdSale->payments->first()->currency);
    }

    public function test_completed_sales_snapshot_their_branch_receipt_profile(): void
    {
        $company = Company::factory()->create([
            'receipt_shop_name' => 'Island Thrift',
            'tax_number' => 'COMPANY-GST',
            'receipt_gst_label' => 'GST No.',
            'receipt_footer' => 'Company footer',
        ]);
        $firstBranch = Branch::factory()->create([
            'company_id' => $company->id,
            'name' => 'Airport Store',
            'receipt_shop_name' => 'Airport Gifts',
            'receipt_tax_number' => 'AIRPORT-GST',
            'receipt_footer' => 'Airport footer',
        ]);
        $secondBranch = Branch::factory()->create([
            'company_id' => $company->id,
            'name' => 'Island Store',
        ]);
        $firstWarehouse = Warehouse::factory()->create(['company_id' => $company->id, 'branch_id' => $firstBranch->id]);
        $secondWarehouse = Warehouse::factory()->create(['company_id' => $company->id, 'branch_id' => $secondBranch->id]);
        $product = Product::factory()->create([
            'company_id' => $company->id,
            'unit_id' => Unit::factory()->create()->id,
            'selling_price' => 20,
            'tax_rate' => 0,
            'track_inventory' => false,
        ]);

        $firstSale = app(SalesService::class)->createSale($company->id, $firstBranch->id, $firstWarehouse->id, [['product_id' => $product->id, 'quantity' => 1]], [['amount' => 20]]);
        $secondSale = app(SalesService::class)->createSale($company->id, $secondBranch->id, $secondWarehouse->id, [['product_id' => $product->id, 'quantity' => 1]], [['amount' => 20]]);

        $this->assertSame('Airport Gifts', $firstSale->receipt_snapshot['shop_name']);
        $this->assertSame('AIRPORT-GST', $firstSale->receipt_snapshot['tax_number']);
        $this->assertSame('Airport footer', $firstSale->receipt_snapshot['footer']);
        $this->assertSame('Island Store', $secondSale->receipt_snapshot['shop_name']);
        $this->assertSame('COMPANY-GST', $secondSale->receipt_snapshot['tax_number']);

        $firstBranch->update(['receipt_shop_name' => 'Changed after sale']);

        $this->assertSame('Airport Gifts', $firstSale->fresh()->receipt_snapshot['shop_name']);
    }
}
