<?php

namespace App\Filament\Resources\Sales\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sale')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('sale_number'),
                                TextEntry::make('status')->badge(),
                                TextEntry::make('sales_channel')->label('Channel')->badge(),
                                TextEntry::make('order_status')->label('Order Status')->badge()->placeholder('—'),
                                TextEntry::make('payment_status')->label('Payment Status')->badge()->placeholder('—'),
                                TextEntry::make('sale_date')->date(),
                                TextEntry::make('customer.name')->label('Customer'),
                                TextEntry::make('customer.phone')->label('Customer Phone')->placeholder('—'),
                                TextEntry::make('customer.email')->label('Customer Email')->placeholder('—'),
                                TextEntry::make('branch.name')->label('Branch'),
                                TextEntry::make('warehouse.name')->label('Warehouse'),
                                TextEntry::make('grand_total')->money('MVR'),
                                TextEntry::make('paid_total')->money('MVR'),
                                TextEntry::make('balance_due')->money('MVR'),
                                TextEntry::make('creator.name')->label('Created By')->placeholder('Website'),
                                TextEntry::make('receipt_print_events_count')->label('Admin Reprints'),
                                TextEntry::make('completed_at')->dateTime(),
                                TextEntry::make('website_payment_method')
                                    ->label('Payment Method')
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'cash' => 'Cash / Pay on Collection',
                                        'bank_transfer' => 'Bank Transfer',
                                        default => $state ?: '—',
                                    })
                                    ->placeholder('—'),
                                TextEntry::make('delivery_method')
                                    ->label('Fulfilment')
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'pickup' => 'Store Pickup',
                                        'local_delivery' => 'Delivery',
                                        default => $state ?: '—',
                                    })
                                    ->badge(),
                                TextEntry::make('delivery_charge')->money('MVR'),
                                TextEntry::make('delivery_address')->columnSpanFull()->placeholder('—'),
                                TextEntry::make('notes')->label('Order Notes')->columnSpanFull(),
                            ]),
                    ]),
                Section::make('Items')
                    ->schema([
                        TextEntry::make('items.product.name')
                            ->label('Products')
                            ->listWithLineBreaks(),
                        TextEntry::make('items.quantity')
                            ->label('Quantities')
                            ->listWithLineBreaks(),
                        TextEntry::make('payments.payment_method')
                            ->label('Payments')
                            ->listWithLineBreaks(),
                    ]),
            ]);
    }
}
