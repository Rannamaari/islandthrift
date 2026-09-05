<?php

namespace App\Filament\Resources\WebsiteOrders\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WebsiteOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('sale_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Placed')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('customer.phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('delivery_method')
                    ->label('Fulfilment')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pickup' => 'Store Pickup',
                        'local_delivery' => 'Delivery',
                        default => $state ?: '—',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'pickup' ? 'gray' : 'info'),
                TextColumn::make('delivery_address')
                    ->label('Delivery Address')
                    ->placeholder('Collect from store')
                    ->limit(40)
                    ->wrap(),
                TextColumn::make('order_status')
                    ->label('Order Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed', 'preparing' => 'info',
                        'ready' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'refunded' => 'gray',
                        default => 'danger',
                    }),
                TextColumn::make('website_payment_method')
                    ->label('Payment Method')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'cash' => 'Cash / Collection',
                        'bank_transfer' => 'Bank Transfer',
                        default => $state ?: '—',
                    })
                    ->toggleable(),
                TextColumn::make('grand_total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, $record): string => "{$record->currency} ".number_format((float) $state, 2))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('delivery_method')
                    ->label('Fulfilment')
                    ->options([
                        'pickup' => 'Store Pickup',
                        'local_delivery' => 'Delivery',
                    ]),
                SelectFilter::make('order_status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'preparing' => 'Preparing',
                        'ready' => 'Ready',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('payment_status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'refunded' => 'Refunded',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
