<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminSupport;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BusinessReports extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Business Reports';

    protected static ?string $title = 'Business Reports';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.business-reports';

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?string $branchId = null;

    public ?string $dailySalesDate = null;

    public static function canAccess(): bool
    {
        return (bool) AdminSupport::user()?->can('reports.view');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('nav.reports');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess() && AdminSupport::companyId(), 403);

        $today = Carbon::now(config('app.business_timezone'))->startOfDay();
        $this->dateFrom = $today->copy()->subDays(29)->toDateString();
        $this->dateTo = $today->toDateString();
        $this->branchId = AdminSupport::user()?->branch_id ?: $this->branches()->keys()->first();
    }

    public function updatedBranchId(): void
    {
        if (! $this->branchId || ! $this->branches()->has($this->branchId)) {
            $this->branchId = $this->branches()->keys()->first();
        }
    }

    public function updatedDateFrom(): void
    {
        if ($this->dateFrom > $this->dateTo) {
            $this->dateTo = $this->dateFrom;
        }
    }

    public function updatedDateTo(): void
    {
        if ($this->dateTo < $this->dateFrom) {
            $this->dateFrom = $this->dateTo;
        }
    }

    public function selectDailySalesDate(string $date): void
    {
        abort_unless($date >= $this->dateFrom && $date <= $this->dateTo, 404);

        $this->dailySalesDate = $date;
    }

    /** @return array<string, mixed> */
    public function getReportProperty(): array
    {
        $branch = $this->selectedBranch();

        if (! $branch) {
            return ['branch' => null, 'summary' => [], 'dailySales' => collect(), 'dailyItemSales' => collect(), 'payments' => collect(), 'bestSellers' => collect(), 'lowStock' => collect()];
        }

        $sales = $this->salesQuery($branch);
        $summary = (clone $sales)
            ->selectRaw('COUNT(*) as transactions, COALESCE(SUM(subtotal), 0) as subtotal, COALESCE(SUM(discount_total), 0) as discounts, COALESCE(SUM(tax_total), 0) as tax, COALESCE(SUM(grand_total), 0) as sales_total, COALESCE(SUM(paid_total), 0) as paid_total, COALESCE(SUM(balance_due), 0) as receivables')
            ->first();
        $grossProfitQuery = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.company_id', $branch->company_id)
            ->where('sales.branch_id', $branch->id)
            ->whereDate('sales.sale_date', '>=', $this->dateFrom)
            ->whereDate('sales.sale_date', '<=', $this->dateTo);
        $grossProfit = (float) Sale::constrainToReportable($grossProfitQuery)
            ->selectRaw('COALESCE(SUM((sale_items.unit_price - sale_items.unit_cost) * sale_items.quantity), 0) as gross_profit')
            ->value('gross_profit');
        $returns = SaleReturn::query()
            ->where('company_id', $branch->company_id)
            ->whereDate('return_date', '>=', $this->dateFrom)
            ->whereDate('return_date', '<=', $this->dateTo)
            ->whereHas('sale', fn (Builder $query) => $query->where('branch_id', $branch->id))
            ->selectRaw('COUNT(*) as returns_count, COALESCE(SUM(grand_total), 0) as returns_total')
            ->first();
        $inventoryValue = $this->inventoryValue($branch);

        $paymentsQuery = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.company_id', $branch->company_id)
            ->where('sales.branch_id', $branch->id)
            ->whereDate('sales.sale_date', '>=', $this->dateFrom)
            ->whereDate('sales.sale_date', '<=', $this->dateTo);
        $payments = Sale::constrainToReportable($paymentsQuery)
            ->selectRaw('sale_payments.payment_method, COALESCE(SUM(sale_payments.amount), 0) as total')
            ->groupBy('sale_payments.payment_method')
            ->orderByDesc('total')
            ->get();

        $bestSellersQuery = Product::query()
            ->join('sale_items', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.company_id', $branch->company_id)
            ->where('sales.branch_id', $branch->id)
            ->whereDate('sales.sale_date', '>=', $this->dateFrom)
            ->whereDate('sales.sale_date', '<=', $this->dateTo);
        $bestSellers = Sale::constrainToReportable($bestSellersQuery)
            ->select('products.id', 'products.name', 'products.sku')
            ->selectRaw('COALESCE(SUM(sale_items.quantity), 0) as quantity_sold, COALESCE(SUM(sale_items.line_total), 0) as sales_total')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('quantity_sold')
            ->limit(10)
            ->get();

        return [
            'branch' => $branch,
            'summary' => [
                'transactions' => (int) ($summary->transactions ?? 0),
                'sales_total' => (float) ($summary->sales_total ?? 0),
                'discounts' => (float) ($summary->discounts ?? 0),
                'tax' => (float) ($summary->tax ?? 0),
                'paid_total' => (float) ($summary->paid_total ?? 0),
                'receivables' => (float) ($summary->receivables ?? 0),
                'gross_profit' => $grossProfit,
                'returns_count' => (int) ($returns->returns_count ?? 0),
                'returns_total' => (float) ($returns->returns_total ?? 0),
                'inventory_value' => $inventoryValue,
            ],
            'dailySales' => $this->dailySales($branch),
            'dailyItemSales' => $this->dailySalesDate ? $this->dailyItemSales($branch, $this->dailySalesDate) : collect(),
            'payments' => $payments,
            'bestSellers' => $bestSellers,
            'lowStock' => $this->lowStock($branch),
        ];
    }

    /** @return Collection<string, string> */
    public function branches(): Collection
    {
        return Branch::query()
            ->where('company_id', AdminSupport::companyId())
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    private function selectedBranch(): ?Branch
    {
        return $this->branchId
            ? Branch::query()->where('company_id', AdminSupport::companyId())->find($this->branchId)
            : null;
    }

    private function salesQuery(Branch $branch): Builder
    {
        return Sale::query()
            ->reportable()
            ->where('company_id', $branch->company_id)
            ->where('branch_id', $branch->id)
            ->whereDate('sale_date', '>=', $this->dateFrom)
            ->whereDate('sale_date', '<=', $this->dateTo);
    }

    private function inventoryValue(Branch $branch): float
    {
        return (float) DB::table('products')
            ->leftJoin('inventory_balances', function ($join) use ($branch): void {
                $join->on('inventory_balances.product_id', '=', 'products.id')
                    ->where('inventory_balances.company_id', '=', $branch->company_id);
            })
            ->leftJoin('warehouses', function ($join) use ($branch): void {
                $join->on('warehouses.id', '=', 'inventory_balances.warehouse_id')
                    ->where('warehouses.branch_id', '=', $branch->id)
                    ->where('warehouses.is_active', true);
            })
            ->where('products.company_id', $branch->company_id)
            ->where('products.track_inventory', true)
            ->selectRaw('COALESCE(SUM(CASE WHEN warehouses.id IS NOT NULL THEN inventory_balances.quantity * products.cost_price ELSE 0 END), 0) as value')
            ->value('value');
    }

    /** @return Collection<int, array{date: string, transactions: int, sales_total: float, returns_total: float, net_sales: float, paid_total: float}> */
    private function dailySales(Branch $branch): Collection
    {
        $returnsByDate = SaleReturn::query()
            ->where('company_id', $branch->company_id)
            ->whereDate('return_date', '>=', $this->dateFrom)
            ->whereDate('return_date', '<=', $this->dateTo)
            ->whereHas('sale', fn (Builder $query) => $query->where('branch_id', $branch->id))
            ->selectRaw('return_date, COALESCE(SUM(grand_total), 0) as returns_total')
            ->groupBy('return_date')
            ->pluck('returns_total', 'return_date');

        return $this->salesQuery($branch)
            ->selectRaw('sale_date, COUNT(*) as transactions, COALESCE(SUM(grand_total), 0) as sales_total, COALESCE(SUM(paid_total), 0) as paid_total')
            ->groupBy('sale_date')
            ->orderByDesc('sale_date')
            ->get()
            ->map(function (Sale $sale) use ($returnsByDate): array {
                $saleDate = $sale->sale_date->toDateString();
                $salesTotal = (float) $sale->sales_total;
                $returnsTotal = (float) ($returnsByDate->get($saleDate) ?? 0);

                return [
                    'date' => $saleDate,
                    'transactions' => (int) $sale->transactions,
                    'sales_total' => $salesTotal,
                    'returns_total' => $returnsTotal,
                    'net_sales' => $salesTotal - $returnsTotal,
                    'paid_total' => (float) $sale->paid_total,
                ];
            });
    }

    /** @return Collection<int, array{product_name: string, sku: string, sales_channel: string, sold_through: string, quantity_sold: float, quantity_returned: float, net_quantity: float, sales_total: float, returns_total: float, net_sales: float}> */
    private function dailyItemSales(Branch $branch, string $date): Collection
    {
        $soldItemsQuery = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.company_id', $branch->company_id)
            ->where('sales.branch_id', $branch->id)
            ->whereDate('sales.sale_date', $date);
        $soldItems = Sale::constrainToReportable($soldItemsQuery)
            ->select('sale_items.product_id', 'sale_items.description')
            ->selectRaw("COALESCE(products.sku, '') as sku")
            ->selectRaw("COALESCE(sales.sales_channel, 'pos') as sales_channel")
            ->selectRaw('COALESCE(SUM(sale_items.quantity), 0) as quantity_sold, COALESCE(SUM(sale_items.line_total), 0) as sales_total')
            ->groupBy('sale_items.product_id', 'sale_items.description', 'products.sku', 'sales.sales_channel')
            ->get()
            ->keyBy(fn (object $item): string => $item->product_id.'|'.$item->sales_channel);

        $returnedItems = SaleReturnItem::query()
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->leftJoin('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->leftJoin('products', 'products.id', '=', 'sale_return_items.product_id')
            ->where('sale_returns.company_id', $branch->company_id)
            ->where('sales.branch_id', $branch->id)
            ->whereDate('sale_returns.return_date', $date)
            ->select('sale_return_items.product_id')
            ->selectRaw("COALESCE(sale_items.description, products.name, 'Deleted product') as product_name")
            ->selectRaw("COALESCE(products.sku, '') as sku")
            ->selectRaw("COALESCE(sales.sales_channel, 'pos') as sales_channel")
            ->selectRaw('COALESCE(SUM(sale_return_items.quantity), 0) as quantity_returned, COALESCE(SUM(sale_return_items.line_total), 0) as returns_total')
            ->groupBy('sale_return_items.product_id', 'sale_items.description', 'products.name', 'products.sku', 'sales.sales_channel')
            ->get()
            ->keyBy(fn (object $item): string => $item->product_id.'|'.$item->sales_channel);

        return $soldItems
            ->union($returnedItems)
            ->map(function (object $item, string $productChannelKey) use ($soldItems, $returnedItems): array {
                $sold = $soldItems->get($productChannelKey);
                $returned = $returnedItems->get($productChannelKey);
                $quantitySold = (float) ($sold?->quantity_sold ?? 0);
                $quantityReturned = (float) ($returned?->quantity_returned ?? 0);
                $salesTotal = (float) ($sold?->sales_total ?? 0);
                $returnsTotal = (float) ($returned?->returns_total ?? 0);
                $salesChannel = $sold?->sales_channel ?? $returned?->sales_channel ?? 'pos';

                return [
                    'product_name' => $sold?->description ?? $returned?->product_name ?? 'Deleted product',
                    'sku' => $sold?->sku ?? $returned?->sku ?? '',
                    'sales_channel' => $salesChannel,
                    'sold_through' => $salesChannel === 'website' ? 'Website' : 'Shop / POS',
                    'quantity_sold' => $quantitySold,
                    'quantity_returned' => $quantityReturned,
                    'net_quantity' => $quantitySold - $quantityReturned,
                    'sales_total' => $salesTotal,
                    'returns_total' => $returnsTotal,
                    'net_sales' => $salesTotal - $returnsTotal,
                ];
            })
            ->sortByDesc('net_sales')
            ->values();
    }

    private function lowStock(Branch $branch): Collection
    {
        return Product::query()
            ->leftJoin('inventory_balances', function ($join) use ($branch): void {
                $join->on('inventory_balances.product_id', '=', 'products.id')
                    ->where('inventory_balances.company_id', '=', $branch->company_id);
            })
            ->leftJoin('warehouses', function ($join) use ($branch): void {
                $join->on('warehouses.id', '=', 'inventory_balances.warehouse_id')
                    ->where('warehouses.branch_id', '=', $branch->id)
                    ->where('warehouses.is_active', true);
            })
            ->where('products.company_id', $branch->company_id)
            ->where('products.is_active', true)
            ->where('products.track_inventory', true)
            ->select('products.id', 'products.name', 'products.sku', 'products.minimum_stock')
            ->selectRaw('COALESCE(SUM(CASE WHEN warehouses.id IS NOT NULL THEN inventory_balances.quantity ELSE 0 END), 0) as current_quantity')
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.minimum_stock')
            ->havingRaw('COALESCE(SUM(CASE WHEN warehouses.id IS NOT NULL THEN inventory_balances.quantity ELSE 0 END), 0) <= products.minimum_stock')
            ->orderBy('current_quantity')
            ->orderBy('products.name')
            ->limit(10)
            ->get();
    }
}
