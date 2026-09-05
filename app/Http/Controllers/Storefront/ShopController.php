<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Services\StorefrontCatalog;
use App\Services\StorefrontContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request, StorefrontContext $context, StorefrontCatalog $catalog): View
    {
        return $this->catalogue($request, $context, $catalog);
    }

    public function category(Request $request, string $slug, StorefrontContext $context, StorefrontCatalog $catalog): View
    {
        $category = Category::query()
            ->where('company_id', $context->company()->id)
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        $request->merge(['category' => $category->id]);

        return $this->catalogue($request, $context, $catalog, $category);
    }

    private function catalogue(Request $request, StorefrontContext $context, StorefrontCatalog $catalog, ?Category $currentCategory = null): View
    {
        $company = $context->company();
        $query = $catalog->query();
        $search = trim((string) $request->string('q'));

        if ($search !== '') {
            $query->where(function ($products) use ($search): void {
                $like = "%{$search}%";
                $products->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhereHas('barcodes', fn ($barcodes) => $barcodes->where('barcode', 'like', $like))
                    ->orWhereHas('brand', fn ($brand) => $brand->where('name', 'like', $like))
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $like));
            });
        }

        $query->when($request->string('category')->isNotEmpty(), fn ($products) => $products->where('category_id', $request->string('category')))
            ->when($request->string('brand')->isNotEmpty(), fn ($products) => $products->where('brand_id', $request->string('brand')))
            ->when($request->filled('min_price'), fn ($products) => $products->where('selling_price', '>=', max(0, (float) $request->input('min_price'))))
            ->when($request->filled('max_price'), fn ($products) => $products->where('selling_price', '<=', max(0, (float) $request->input('max_price'))))
            ->when($request->boolean('in_stock'), fn ($products) => $products->where(function ($stock): void {
                $stock->where('track_inventory', false)->orWhereHas('inventoryBalances', fn ($balances) => $balances->where('warehouse_id', app(StorefrontContext::class)->warehouse()->id)->where('quantity', '>', 0));
            }));

        match ($request->input('sort', 'newest')) {
            'price_asc' => $query->orderBy('selling_price'),
            'price_desc' => $query->orderByDesc('selling_price'),
            'name' => $query->orderBy('name'),
            default => $query->latest(),
        };

        $products = $query->paginate(12)->withQueryString();
        $categories = Category::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereHas('products', fn ($products) => $products->visibleOnline())
            ->orderBy('name')
            ->get();
        $brands = Brand::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereHas('products', fn ($products) => $products->visibleOnline())
            ->orderBy('name')
            ->get();

        return view('storefront.shop', compact('company', 'products', 'categories', 'brands', 'currentCategory'));
    }
}
