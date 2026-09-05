<x-filament-panels::page>
    @php($report = $this->report)
    @php($branch = $report['branch'])
    @php($summary = $report['summary'])
    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="grid gap-4 md:grid-cols-3">
                <label class="text-sm font-medium">Branch
                    <select wire:model.live="branchId" class="mt-2 w-full rounded-lg border-gray-300 dark:border-white/15 dark:bg-gray-900">
                        @foreach ($this->branches() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-medium">From
                    <input wire:model.live="dateFrom" type="date" class="mt-2 w-full rounded-lg border-gray-300 dark:border-white/15 dark:bg-gray-900">
                </label>
                <label class="text-sm font-medium">To
                    <input wire:model.live="dateTo" type="date" class="mt-2 w-full rounded-lg border-gray-300 dark:border-white/15 dark:bg-gray-900">
                </label>
            </div>
            @if ($branch)<p class="mt-4 text-sm text-gray-600 dark:text-gray-300">All amounts are in <strong>{{ $branch->currency }}</strong>. Select one branch at a time so USD and MVR are never mixed.</p>@endif
        </section>

        @if ($branch)
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5"><p class="text-sm text-gray-500">Sales</p><p class="mt-2 text-2xl font-bold">{{ $branch->currency }} {{ number_format($summary['sales_total'], 2) }}</p><p class="mt-1 text-sm text-gray-500">{{ $summary['transactions'] }} transactions</p></div>
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5"><p class="text-sm text-gray-500">Gross Profit</p><p class="mt-2 text-2xl font-bold text-success-600">{{ $branch->currency }} {{ number_format($summary['gross_profit'], 2) }}</p><p class="mt-1 text-sm text-gray-500">Before overheads</p></div>
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5"><p class="text-sm text-gray-500">Receivables</p><p class="mt-2 text-2xl font-bold text-warning-600">{{ $branch->currency }} {{ number_format($summary['receivables'], 2) }}</p><p class="mt-1 text-sm text-gray-500">Outstanding customer credit</p></div>
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5"><p class="text-sm text-gray-500">Inventory Value</p><p class="mt-2 text-2xl font-bold">{{ $branch->currency }} {{ number_format($summary['inventory_value'], 2) }}</p><p class="mt-1 text-sm text-gray-500">Active branch warehouses</p></div>
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <h2 class="text-lg font-semibold">Sales and Payment Summary</h2>
                    <dl class="mt-4 space-y-3 text-sm"><div class="flex justify-between"><dt>Discounts</dt><dd>{{ $branch->currency }} {{ number_format($summary['discounts'], 2) }}</dd></div><div class="flex justify-between"><dt>Tax collected</dt><dd>{{ $branch->currency }} {{ number_format($summary['tax'], 2) }}</dd></div><div class="flex justify-between"><dt>Payments received</dt><dd>{{ $branch->currency }} {{ number_format($summary['paid_total'], 2) }}</dd></div><div class="flex justify-between border-t pt-3"><dt>Returns ({{ $summary['returns_count'] }})</dt><dd>{{ $branch->currency }} {{ number_format($summary['returns_total'], 2) }}</dd></div></dl>
                    <h3 class="mt-6 text-sm font-semibold uppercase tracking-wide text-gray-500">Tender Mix</h3>
                    <dl class="mt-3 space-y-2 text-sm">@forelse ($report['payments'] as $payment)<div class="flex justify-between"><dt>{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</dt><dd>{{ $branch->currency }} {{ number_format((float) $payment->total, 2) }}</dd></div>@empty <p class="text-gray-500">No payments recorded for this period.</p>@endforelse</dl>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <h2 class="text-lg font-semibold">Best Sellers</h2>
                    <div class="mt-4 overflow-x-auto"><table class="w-full text-sm"><thead class="text-left text-gray-500"><tr><th class="pb-3">Product</th><th class="pb-3 text-right">Qty</th><th class="pb-3 text-right">Sales</th></tr></thead><tbody>@forelse ($report['bestSellers'] as $product)<tr class="border-t border-gray-100 dark:border-white/10"><td class="py-3"><strong>{{ $product->name }}</strong><br><span class="text-xs text-gray-500">{{ $product->sku }}</span></td><td class="py-3 text-right">{{ number_format((float) $product->quantity_sold, 2) }}</td><td class="py-3 text-right">{{ $branch->currency }} {{ number_format((float) $product->sales_total, 2) }}</td></tr>@empty <tr><td colspan="3" class="py-4 text-gray-500">No sales in this period.</td></tr>@endforelse</tbody></table></div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <div><h2 class="text-lg font-semibold">Daily Sales</h2><p class="text-sm text-gray-500">Completed sales and returns by sale date for the selected branch.</p></div>
                <div class="mt-4 overflow-x-auto"><table class="w-full text-sm"><thead class="text-left text-gray-500"><tr><th class="pb-3">Date</th><th class="pb-3 text-right">Transactions</th><th class="pb-3 text-right">Gross Sales</th><th class="pb-3 text-right">Returns</th><th class="pb-3 text-right">Net Sales</th><th class="pb-3 text-right">Payments</th></tr></thead><tbody>@forelse ($report['dailySales'] as $day)<tr class="border-t border-gray-100 dark:border-white/10"><td class="py-3 font-medium"><button type="button" wire:click="selectDailySalesDate('{{ $day['date'] }}')" class="text-primary-600 hover:underline">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('M d, Y') }}</button></td><td class="py-3 text-right">{{ $day['transactions'] }}</td><td class="py-3 text-right">{{ $branch->currency }} {{ number_format($day['sales_total'], 2) }}</td><td class="py-3 text-right text-danger-600">{{ $branch->currency }} {{ number_format($day['returns_total'], 2) }}</td><td class="py-3 text-right font-semibold">{{ $branch->currency }} {{ number_format($day['net_sales'], 2) }}</td><td class="py-3 text-right">{{ $branch->currency }} {{ number_format($day['paid_total'], 2) }}</td></tr>@empty <tr><td colspan="6" class="py-4 text-gray-500">No sales recorded for this period.</td></tr>@endforelse</tbody></table></div>
            </section>

            @if ($dailySalesDate)
                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div><h2 class="text-lg font-semibold">Daily Item Sales Summary</h2><p class="text-sm text-gray-500">{{ \Illuminate\Support\Carbon::parse($dailySalesDate)->format('M d, Y') }}. Click another date above to change this summary.</p></div>
                    <div class="mt-4 overflow-x-auto"><table class="w-full text-sm"><thead class="text-left text-gray-500"><tr><th class="pb-3">Product</th><th class="pb-3">SKU</th><th class="pb-3">Sold Through</th><th class="pb-3 text-right">Sold</th><th class="pb-3 text-right">Returned</th><th class="pb-3 text-right">Net Qty</th><th class="pb-3 text-right">Gross Sales</th><th class="pb-3 text-right">Returns</th><th class="pb-3 text-right">Net Sales</th></tr></thead><tbody>@forelse ($report['dailyItemSales'] as $item)<tr class="border-t border-gray-100 dark:border-white/10"><td class="py-3 font-medium">{{ $item['product_name'] }}</td><td class="py-3">{{ $item['sku'] ?: '—' }}</td><td class="py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $item['sales_channel'] === 'website' ? 'bg-info-50 text-info-700 dark:bg-info-400/10 dark:text-info-400' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' }}">{{ $item['sold_through'] }}</span></td><td class="py-3 text-right">{{ number_format($item['quantity_sold'], 2) }}</td><td class="py-3 text-right text-danger-600">{{ number_format($item['quantity_returned'], 2) }}</td><td class="py-3 text-right font-semibold">{{ number_format($item['net_quantity'], 2) }}</td><td class="py-3 text-right">{{ $branch->currency }} {{ number_format($item['sales_total'], 2) }}</td><td class="py-3 text-right text-danger-600">{{ $branch->currency }} {{ number_format($item['returns_total'], 2) }}</td><td class="py-3 text-right font-semibold">{{ $branch->currency }} {{ number_format($item['net_sales'], 2) }}</td></tr>@empty <tr><td colspan="9" class="py-4 text-gray-500">No item sales or returns recorded for this date.</td></tr>@endforelse</tbody></table></div>
                </section>
            @endif

            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <div class="flex items-center justify-between gap-3"><div><h2 class="text-lg font-semibold">Low Stock Alerts</h2><p class="text-sm text-gray-500">The ten products at or below their minimum level across this branch’s active warehouses.</p></div><a class="text-sm font-medium text-primary-600" href="{{ url('/admin/inventory-overview') }}">Open inventory overview</a></div>
                <div class="mt-4 overflow-x-auto"><table class="w-full text-sm"><thead class="text-left text-gray-500"><tr><th class="pb-3">Product</th><th class="pb-3">SKU</th><th class="pb-3 text-right">On Hand</th><th class="pb-3 text-right">Minimum</th></tr></thead><tbody>@forelse ($report['lowStock'] as $product)<tr class="border-t border-gray-100 dark:border-white/10"><td class="py-3 font-medium">{{ $product->name }}</td><td class="py-3">{{ $product->sku }}</td><td class="py-3 text-right text-danger-600">{{ number_format((float) $product->current_quantity, 2) }}</td><td class="py-3 text-right">{{ number_format((float) $product->minimum_stock, 2) }}</td></tr>@empty <tr><td colspan="4" class="py-4 text-gray-500">No low-stock products for this branch.</td></tr>@endforelse</tbody></table></div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
