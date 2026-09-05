<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\StorefrontCatalog;
use Illuminate\Contracts\View\View;

class ProductController extends Controller
{
    public function __invoke(string $slug, StorefrontCatalog $catalog): View
    {
        $product = $catalog->query()->where('slug', $slug)->firstOrFail();
        $related = $catalog->query()
            ->whereKeyNot($product->id)
            ->when($product->category_id, fn ($query) => $query->where('category_id', $product->category_id))
            ->limit(4)
            ->get();

        return view('storefront.product', compact('product', 'related'));
    }
}
