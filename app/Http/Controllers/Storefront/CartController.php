<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\StorefrontCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(StorefrontCatalog $catalog): View
    {
        $cart = session('store_cart', []);
        $products = $catalog->query()->whereIn('id', array_keys($cart))->get()->keyBy('id');
        $items = collect($cart)->map(function ($quantity, $productId) use ($products, $catalog) {
            $product = $products->get($productId);

            return $product ? ['product' => $product, 'quantity' => (int) $quantity, 'price' => $catalog->price($product)] : null;
        })->filter();

        return view('storefront.cart', ['items' => $items, 'subtotal' => $items->sum(fn ($item) => $item['price'] * $item['quantity'])]);
    }

    public function store(Request $request, StorefrontCatalog $catalog): RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'uuid'], 'quantity' => ['required', 'integer', 'min:1', 'max:999']]);
        $product = $catalog->query()->findOrFail($data['product_id']);
        $cart = session('store_cart', []);
        $quantity = ($cart[$product->id] ?? 0) + $data['quantity'];

        if ($quantity > $catalog->available($product)) {
            return back()->withErrors(['quantity' => 'The requested quantity is not available.']);
        }

        $cart[$product->id] = $quantity;
        session(['store_cart' => $cart]);

        return back()->with('success', "{$product->name} added to your cart.");
    }

    public function update(Request $request, string $productId, StorefrontCatalog $catalog): RedirectResponse
    {
        $quantity = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:999']])['quantity'];
        $product = $catalog->query()->findOrFail($productId);

        if ($quantity > $catalog->available($product)) {
            return back()->withErrors(['quantity' => 'Only '.(int) $catalog->available($product)." available for {$product->name}."]);
        }

        $cart = session('store_cart', []);
        $cart[$productId] = $quantity;
        session(['store_cart' => $cart]);

        return back()->with('success', 'Cart updated.');
    }

    public function destroy(string $productId): RedirectResponse
    {
        $cart = session('store_cart', []);
        unset($cart[$productId]);
        session(['store_cart' => $cart]);

        return back()->with('success', 'Item removed from your cart.');
    }
}
