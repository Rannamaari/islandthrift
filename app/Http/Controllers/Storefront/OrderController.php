<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Contracts\View\View;

class OrderController extends Controller
{
    public function __invoke(string $token): View
    {
        $order = Sale::query()
            ->where('sales_channel', 'website')
            ->where('tracking_token', $token)
            ->with(['items.product', 'customer', 'branch'])
            ->firstOrFail();

        return view('storefront.order', compact('order'));
    }
}
