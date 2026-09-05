<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class StorefrontCatalog
{
    public function __construct(private readonly StorefrontContext $context) {}

    public function query(): Builder
    {
        $company = $this->context->company();
        $branch = $this->context->branch();
        $warehouse = $this->context->warehouse();

        return Product::query()
            ->visibleOnline()
            ->where('products.company_id', $company->id)
            ->with(['category:id,name,slug', 'brand:id,name'])
            ->withSum(['inventoryBalances as online_stock' => fn ($query) => $query->where('warehouse_id', $warehouse->id)], 'quantity')
            ->with(['branchPrices' => fn ($query) => $query->where('branch_id', $branch->id)]);
    }

    public function price(Product $product): float
    {
        $regular = (float) ($product->branchPrices->first()?->selling_price ?? $product->selling_price);

        return $product->sale_price !== null && (float) $product->sale_price < $regular
            ? (float) $product->sale_price
            : $regular;
    }

    public function regularPrice(Product $product): float
    {
        return (float) ($product->branchPrices->first()?->selling_price ?? $product->selling_price);
    }

    public function available(Product $product): float
    {
        return $product->track_inventory ? max(0, (float) ($product->online_stock ?? 0)) : PHP_FLOAT_MAX;
    }
}
