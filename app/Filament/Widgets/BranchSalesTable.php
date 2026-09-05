<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AdminSupport;
use App\Models\Branch;
use App\Models\Sale;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BranchSalesTable extends TableWidget
{
    protected static ?string $heading = 'Branch Performance Today';

    protected string|int|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->columns([
                TextColumn::make('name')->label('Branch')->description(fn (Branch $record): string => $record->code)->weight('bold'),
                TextColumn::make('currency')->badge(),
                TextColumn::make('today_sales')->label("Today's Sales")->formatStateUsing(fn ($state, Branch $record): string => "{$record->currency} ".number_format((float) $state, 2)),
                TextColumn::make('today_transactions')->label('Transactions')->badge()->color('success'),
                TextColumn::make('low_stock_count')->label('Low Stock')->badge()->color(fn ($state): string => (int) $state > 0 ? 'warning' : 'success'),
                TextColumn::make('out_of_stock_count')->label('Out of Stock')->badge()->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'success'),
            ])
            ->paginated(false);
    }

    /** @return Builder<Branch> */
    private function query(): Builder
    {
        $companyId = AdminSupport::companyId();
        $today = today()->toDateString();

        return Branch::query()
            ->where('branches.company_id', $companyId)
            ->where('branches.is_active', true)
            ->select('branches.*')
            ->selectSub(
                Sale::query()->reportable()->selectRaw('COALESCE(SUM(grand_total), 0)')->whereColumn('sales.branch_id', 'branches.id')->whereDate('sale_date', $today),
                'today_sales',
            )
            ->selectSub(
                Sale::query()->reportable()->selectRaw('COUNT(*)')->whereColumn('sales.branch_id', 'branches.id')->whereDate('sale_date', $today),
                'today_transactions',
            )
            ->selectSub(
                DB::table('products')
                    ->crossJoin('warehouses')
                    ->leftJoin('inventory_balances', function ($join): void {
                        $join->on('inventory_balances.product_id', '=', 'products.id')
                            ->on('inventory_balances.warehouse_id', '=', 'warehouses.id');
                    })
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('warehouses.branch_id', 'branches.id')
                    ->whereColumn('products.company_id', 'branches.company_id')
                    ->where('products.track_inventory', true)
                    ->where(function ($query): void {
                        $query->whereNull('inventory_balances.id')
                            ->orWhereRaw('inventory_balances.quantity <= products.minimum_stock');
                    }),
                'low_stock_count',
            )
            ->selectSub(
                DB::table('products')
                    ->crossJoin('warehouses')
                    ->leftJoin('inventory_balances', function ($join): void {
                        $join->on('inventory_balances.product_id', '=', 'products.id')
                            ->on('inventory_balances.warehouse_id', '=', 'warehouses.id');
                    })
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('warehouses.branch_id', 'branches.id')
                    ->whereColumn('products.company_id', 'branches.company_id')
                    ->where('products.track_inventory', true)
                    ->where(function ($query): void {
                        $query->whereNull('inventory_balances.id')
                            ->orWhere('inventory_balances.quantity', '<=', 0);
                    }),
                'out_of_stock_count',
            )
            ->orderBy('name');
    }
}
