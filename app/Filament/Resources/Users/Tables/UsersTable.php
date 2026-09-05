<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('phone')->searchable()->toggleable(),
                TextColumn::make('branch.name')->toggleable(),
                TextColumn::make('warehouse.name')->toggleable(),
                TextColumn::make('roles.name')
                    ->badge()
                    ->separator(','),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('last_login_at')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->relationship('branch', 'name'),
                SelectFilter::make('is_active')
                    ->options([1 => 'Active', 0 => 'Inactive']),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
