<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Date & Time')->dateTime('d M Y, h:i:s A')->sortable(),
                TextColumn::make('actor_name')->label('Changed By')->placeholder('System / Website')->description(fn ($record): ?string => $record->actor_email)->searchable(['actor_name', 'actor_email']),
                TextColumn::make('event')->badge()->color(fn (string $state): string => match ($state) {
                    'created' => 'success',
                    'updated' => 'warning',
                    'deleted' => 'danger',
                    default => 'gray',
                })->sortable(),
                TextColumn::make('entity_type')->label('Area')->formatStateUsing(fn (string $state): string => class_basename($state))->badge()->searchable(),
                TextColumn::make('entity_label')->label('Record')->searchable()->limit(45),
                TextColumn::make('changed_fields')->label('Changed Fields')->state(fn ($record): string => collect(array_keys($record->new_values ?? $record->old_values ?? []))->implode(', '))->limit(60)->wrap(),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')->options([
                    'created' => 'Created',
                    'updated' => 'Updated',
                    'deleted' => 'Deleted',
                ]),
                SelectFilter::make('entity_type')->label('Area')->options([
                    'App\\Models\\Product' => 'Products / Prices',
                    'App\\Models\\ProductBranchPrice' => 'Branch Prices',
                    'App\\Models\\Sale' => 'Sales / Website Orders',
                    'App\\Models\\SaleItem' => 'Sale Items / Discounts',
                    'App\\Models\\SalePayment' => 'Sale Payments',
                    'App\\Models\\SaleReturn' => 'Sale Returns',
                    'App\\Models\\SaleReturnItem' => 'Returned Items',
                    'App\\Models\\StockMovement' => 'Stock Movements',
                    'App\\Models\\CashierShift' => 'Counter Shifts',
                    'App\\Models\\User' => 'Users',
                    'App\\Models\\Customer' => 'Customers',
                    'App\\Models\\Purchase' => 'Purchases',
                ])->searchable(),
                SelectFilter::make('actor_id')->label('Changed By')->relationship('actor', 'name')->searchable()->preload(),
                Filter::make('created_at')->schema([
                    DatePicker::make('from'),
                    DatePicker::make('until'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([]);
    }
}
