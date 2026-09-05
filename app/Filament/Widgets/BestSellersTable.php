<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AdminSupport;
use App\Models\Product;
use App\Models\Sale;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class BestSellersTable extends TableWidget
{
    protected static ?string $heading = 'Best Sellers';

    protected string|int|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->description('Top products by quantity sold in the last 30 days for your active warehouse.')
            ->query($this->query())
            ->columns([
                TextColumn::make('name')->label('Product')->description(fn (Product $record): string => "SKU: {$record->sku}")->limit(28),
                TextColumn::make('quantity_sold')->label('Sold')->numeric(decimalPlaces: 2)->alignEnd(),
                TextColumn::make('sales_total')->label('Sales')->formatStateUsing(fn ($state, Product $record): string => ($record->sales_currency ?? '').' '.number_format((float) $state, 2))->alignEnd(),
            ])
            ->defaultPaginationPageOption(5);
    }

    /** @return Builder<Product> */
    private function query(): Builder
    {
        $companyId = AdminSupport::companyId();
        $warehouseId = AdminSupport::activeWarehouseId();

        if (! $companyId || ! $warehouseId) {
            return Product::query()->whereRaw('1 = 0');
        }

        $query = Product::query()
            ->where('products.company_id', $companyId)
            ->join('sale_items', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.warehouse_id', $warehouseId)
            ->whereDate('sales.sale_date', '>=', today()->subDays(29)->toDateString());

        return Sale::constrainToReportable($query)
            ->select('products.*')
            ->selectRaw('SUM(sale_items.quantity) as quantity_sold')
            ->selectRaw('SUM(sale_items.line_total) as sales_total')
            ->selectRaw('MIN(sales.currency) as sales_currency')
            ->groupBy('products.id')
            ->orderByDesc('quantity_sold');
    }
}
