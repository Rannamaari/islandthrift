<?php

namespace App\Filament\Resources\WebsiteOrders;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Sales\Schemas\SaleInfolist;
use App\Filament\Resources\WebsiteOrders\Pages\ListWebsiteOrders;
use App\Filament\Resources\WebsiteOrders\Pages\ViewWebsiteOrder;
use App\Filament\Resources\WebsiteOrders\Tables\WebsiteOrdersTable;
use App\Models\Sale;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class WebsiteOrderResource extends BaseResource
{
    protected static ?string $model = Sale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $recordTitleAttribute = 'sale_number';

    protected static ?string $modelLabel = 'website order';

    protected static ?string $pluralModelLabel = 'website orders';

    protected static ?string $viewPermission = 'sales.view';

    public static function infolist(Schema $schema): Schema
    {
        return SaleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebsiteOrdersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('sales_channel', 'website')
            ->with(['customer', 'branch', 'warehouse', 'creator', 'items.product', 'payments'])
            ->withCount('receiptPrintEvents');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('nav.sales');
    }

    public static function getNavigationLabel(): string
    {
        return 'Website Orders';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->whereIn('order_status', ['pending', 'confirmed', 'preparing', 'ready'])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebsiteOrders::route('/'),
            'view' => ViewWebsiteOrder::route('/{record}'),
        ];
    }
}
