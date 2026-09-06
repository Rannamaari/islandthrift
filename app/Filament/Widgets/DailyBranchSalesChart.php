<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AdminSupport;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\Warehouse;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class DailyBranchSalesChart extends ChartWidget
{
    protected ?string $heading = 'Daily Sales';

    protected ?string $description = 'Last 14 days for your active branch';

    protected string|int|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $companyId = AdminSupport::companyId();
        $warehouseId = AdminSupport::activeWarehouseId();
        $branchId = $warehouseId ? Warehouse::query()->where('company_id', $companyId)->whereKey($warehouseId)->value('branch_id') : null;
        $branch = $branchId ? Branch::query()->where('company_id', $companyId)->find($branchId) : null;
        $today = Carbon::now(config('app.business_timezone'))->startOfDay();
        $start = $today->copy()->subDays(13)->toDateString();
        $dates = collect(range(0, 13))->map(fn (int $day): Carbon => $today->copy()->subDays(13 - $day));
        $totals = $branch
            ? Sale::query()
                ->reportable()
                ->where('company_id', $companyId)
                ->where('branch_id', $branch->id)
                ->whereDate('sale_date', '>=', $start)
                ->selectRaw('sale_date, COALESCE(SUM(grand_total), 0) as total')
                ->groupBy('sale_date')
                ->get()
                ->mapWithKeys(fn (Sale $sale): array => [
                    $sale->sale_date->toDateString() => (float) $sale->total,
                ])
            : collect();

        return [
            'datasets' => [[
                'label' => $branch ? "{$branch->name} ({$branch->currency})" : 'No active branch',
                'data' => $dates->map(fn (Carbon $date): float => (float) ($totals[$date->toDateString()] ?? 0))->all(),
                'borderColor' => '#d97706',
                'backgroundColor' => 'rgba(217, 119, 6, 0.12)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $dates->map(fn (Carbon $date): string => $date->format('M j'))->all(),
        ];
    }
}
